<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceLog;
use App\Enums\LogLevel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProcessManager
{
    protected string $logsDir;

    protected string $scriptsDir;

    public function __construct(protected PowerShell $powerShell = new PowerShell())
    {
        $this->logsDir = storage_path('logs/services');
        $this->scriptsDir = storage_path('app/services');

        File::ensureDirectoryExists($this->logsDir);
        File::ensureDirectoryExists($this->scriptsDir);
    }

    /**
     * Inicia o processo do serviço em segundo plano.
     *
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function start(Service $service, bool $resetRestartCount = true): array
    {
        if ($service->isRunning()) {
            return ['ok' => false, 'message' => __('services.messages.already_running')];
        }

        if ($service->isPending()) {
            return ['ok' => false, 'message' => __('services.messages.already_pending')];
        }

        $tokens = $this->tokenize($service->command);

        if (count($tokens) === 0) {
            return $this->fail($service, __('services.messages.empty_command'));
        }

        $dir = $service->working_directory ?: base_path();

        if ($service->port && $this->checkPort($service->port)) {
            $this->killProcessOnPort($service->port, true);
            usleep(500000);
        }

        $resolution = $this->resolveExecutable($tokens[0], $dir);

        if (! $resolution['ok']) {
            return $this->fail($service, $resolution['error']);
        }

        $exe = $resolution['path'];
        $args = array_slice($tokens, 1);

        $outPath = $this->logsDir
            . DIRECTORY_SEPARATOR
            . $service->slug
            . '-' . now()->format('Ymd-His')
            . '-' . Str::lower(Str::random(4))
            . '.out.log';
        $errPath = Str::replaceLast('.out.log', '.err.log', $outPath);

        $env = HostEnvironment::forChild($service->env_vars ?? []);

        $result = $this->runPowerShell($this->buildStartScript($exe, $args, $dir, $outPath, $errPath), env: $env);

        $output = trim(implode("\n", $result['output']));

        if (preg_match('/^PID\|(\d+)$/m', $output, $matches)) {
            $pid = (int) $matches[1];

            $service->update([
                'status' => ServiceStatus::Running,
                'pid' => $pid,
                'started_at' => now(),
                'exit_code' => null,
                'last_error' => null,
                'restart_count' => $resetRestartCount ? 0 : $service->restart_count,
                'log_path_out' => $outPath,
                'log_path_err' => $errPath,
                // Arquivos novos a cada start: a leitura incremental recomeça do zero.
                'log_offset_out' => 0,
                'log_offset_err' => 0,
                'cpu_usage' => null,
                'cpu_seconds_total' => null,
                'memory_kb' => null,
                'uptime_seconds' => 0,
                'port_open' => $service->port ? $this->checkPort($service->port) : null,
                'last_metrics_at' => null,
            ]);

            $this->logSystem($service, __('services.messages.started', ['pid' => $pid]));

            return ['ok' => true, 'message' => __('services.messages.started', ['pid' => $pid]), 'data' => ['pid' => $pid]];
        }

        if (preg_match('/^EXITED\|(\d+)\|(.*)$/ms', $output, $matches)) {
            $code = (int) $matches[1];
            $error = trim($matches[2]);

            if ($error === '') {
                if ($service->port && $this->checkPort($service->port)) {
                    $error = __('services.messages.port_in_use', ['port' => $service->port]);
                } else {
                    $error = __('services.messages.start_failed');
                }
            }

            $service->update([
                'status' => ServiceStatus::Error,
                'pid' => null,
                'exit_code' => $code,
                'last_error' => $error,
                'started_at' => null,
            ]);

            $this->logSystem($service, $error, ServiceLog::TYPE_STDERR);

            return ['ok' => false, 'message' => $error, 'data' => ['exit_code' => $code]];
        }

        $error = trim($output) ?: __('services.messages.unknown_start_error');

        return $this->fail($service, $error);
    }

    /**
     * Para o processo de forma graciosa, com timeout, e força se necessário.
     *
     * @return array{ok: bool, message: string}
     */
    public function stop(Service $service): array
    {
        $pid = $service->pid;

        if (! $pid) {
            $this->markStopped($service);

            return ['ok' => true, 'message' => __('services.messages.not_running')];
        }

        $result = $this->runPowerShell($this->buildStopScript($pid, $service->stop_timeout ?: 10));

        $this->markStopped($service);
        $this->logSystem($service, __('services.messages.stopped', ['pid' => $pid]));

        if ($service->port) {
            $this->killProcessOnPort($service->port);
        }

        return ['ok' => true, 'message' => __('services.messages.stopped', ['pid' => $pid])];
    }

    /**
     * Mata o processo imediatamente (taskkill /T /F).
     *
     * @return array{ok: bool, message: string}
     */
    public function kill(Service $service): array
    {
        $pid = $service->pid;

        if (! $pid) {
            $this->markStopped($service);

            return ['ok' => true, 'message' => __('services.messages.not_running')];
        }

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "& taskkill /PID $pid /T /F 2>&1 | Out-Null;"
            . "if (\$LASTEXITCODE -eq 0) { 'KILLED' } else { 'FAILED' }";

        $result = $this->runPowerShell($script);

        $this->markStopped($service);
        $this->logSystem($service, __('services.messages.killed', ['pid' => $pid]));

        if ($service->port) {
            $this->killProcessOnPort($service->port);
        }

        return [
            'ok' => str_contains(trim(implode("\n", $result['output'])), 'KILLED'),
            'message' => __('services.messages.killed', ['pid' => $pid]),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function restart(Service $service): array
    {
        $this->stop($service);

        return $this->start($service);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    protected function fail(Service $service, string $error): array
    {
        $service->update([
            'status' => ServiceStatus::Error,
            'pid' => null,
            'last_error' => $error,
            'started_at' => null,
        ]);

        $this->logSystem($service, $error, ServiceLog::TYPE_STDERR);

        return ['ok' => false, 'message' => $error];
    }

    public function isAlive(int $pid): bool
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "if (Get-Process -Id $pid -ErrorAction SilentlyContinue) { 'ALIVE' } else { 'DEAD' }";

        $result = $this->runPowerShell($script);

        return str_contains(trim(implode("\n", $result['output'])), 'ALIVE');
    }

    /**
     * @return array{cpu_seconds: float, memory_kb: int, started_at: string}|null
     */
    public function metrics(int $pid): ?array
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$p = Get-Process -Id $pid -ErrorAction SilentlyContinue;"
            . "if (\$null -eq \$p) { 'DEAD'; exit 0 };"
            . "Write-Output ('CPU|' + [math]::Round(\$p.CPU, 3));"
            . "Write-Output ('MEM|' + [math]::Round(\$p.WorkingSet64 / 1KB, 0));"
            . "Write-Output ('START|' + \$p.StartTime.ToString('yyyy-MM-dd HH:mm:ss'));";

        $result = $this->runPowerShell($script);

        $output = trim(implode("\n", $result['output']));

        if (! str_contains($output, 'CPU|')) {
            return null;
        }

        $metrics = ['cpu_seconds' => 0.0, 'memory_kb' => 0, 'started_at' => ''];

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^CPU\|([0-9.]+)$/', trim($line), $m)) {
                $metrics['cpu_seconds'] = (float) $m[1];
            } elseif (preg_match('/^MEM\|(\d+)$/', trim($line), $m)) {
                $metrics['memory_kb'] = (int) $m[1];
            } elseif (preg_match('/^START\|(.+)$/', trim($line), $m)) {
                $metrics['started_at'] = trim($m[1]);
            }
        }

        return $metrics;
    }

    /**
     * Coleta, em UMA única chamada ao PowerShell, o estado de vários processos
     * e a lista de portas em escuta. Evita abrir 2 processos do PowerShell por
     * serviço (o que inviabilizava o polling do painel).
     *
     * @param  list<int>  $pids
     * @return array{procs: array<int, array{cpu_seconds: float, memory_kb: int}|null>, ports: list<int>}
     */
    public function probe(array $pids): array
    {
        $pids = array_values(array_unique(array_filter($pids)));

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$listening = New-Object System.Collections.Generic.HashSet[string];"
            . "foreach (\$line in (& netstat -ano 2>\$null | Select-String 'LISTENING')) {"
            . "  if (\$line -match ':(\\d+)\\s') { [void]\$listening.Add(\$Matches[1]) }"
            . "};"
            . "Write-Output ('PORTS|' + ((\$listening) -join ','));";

        if ($pids !== []) {
            $list = implode(',', $pids);

            $script .= "foreach (\$id in @($list)) {"
                . "  \$p = Get-Process -Id \$id -ErrorAction SilentlyContinue;"
                . "  if (\$null -eq \$p) { Write-Output ('PROC|' + \$id + '|DEAD') }"
                . "  else { Write-Output ('PROC|' + \$id + '|' + [math]::Round(\$p.CPU, 3) + '|' + [math]::Round(\$p.WorkingSet64 / 1KB, 0)) }"
                . "};";
        }

        $result = $this->runPowerShell($script);

        $procs = array_fill_keys($pids, null);
        $ports = [];

        foreach (explode("\n", implode("\n", $result['output'])) as $line) {
            $line = trim($line);

            if (preg_match('/^PORTS\|(.*)$/', $line, $m)) {
                $ports = array_values(array_filter(array_map('intval', explode(',', $m[1]))));
            } elseif (preg_match('/^PROC\|(\d+)\|([0-9.]+)\|(\d+)$/', $line, $m)) {
                $procs[(int) $m[1]] = [
                    'cpu_seconds' => (float) $m[2],
                    'memory_kb' => (int) $m[3],
                ];
            }
        }

        return ['procs' => $procs, 'ports' => $ports];
    }

    public function checkPort(?int $port): bool
    {
        if (! $port) {
            return false;
        }

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$listening = & netstat -ano 2>\$null | Select-String ':$port' | Select-String 'LISTENING';"
            . "if (\$listening) { 'OPEN' } else { 'CLOSED' }";

        $result = $this->runPowerShell($script);

        return str_contains(trim(implode("\n", $result['output'])), 'OPEN');
    }

    /**
     * Mata os processos que estejam escutando na porta informada.
     * Usado para limpar processos-orphãos (ex.: o servidor `php -S` filho
     * do `php artisan serve`, que sobrevive ao encerramento do pai).
     */
    public function killProcessOnPort(?int $port, bool $onlyPhp = false): void
    {
        if (! $port) {
            return;
        }

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$lines = netstat -ano 2>\$null | Select-String ':$port ' | Select-String 'LISTENING';"
            . "foreach (\$line in \$lines) {"
            . "  if (\$line -match '(\d+)\s*\$') {"
            . "    \$listenerPid = \$Matches[1];"
            . "    \$proc = Get-Process -Id \$listenerPid -ErrorAction SilentlyContinue;"
            . ($onlyPhp
                ? "    if (\$proc -and \$proc.Path -like '*php*') { & taskkill /PID \$listenerPid /T /F 2>\$null };"
                : "    & taskkill /PID \$listenerPid /T /F 2>\$null;")
            . "  }"
            . "}";

        $this->runPowerShell($script);
    }

    public function tailLog(Service $service, int $lines = 100): string
    {
        $parts = [];

        foreach ([$service->log_path_out, $service->log_path_err] as $path) {
            if ($path && File::exists($path)) {
                $content = $this->tailFile($path, $lines);

                if (trim($content) !== '') {
                    $parts[] = $content;
                }
            }
        }

        if ($parts === []) {
            return __('services.messages.no_logs');
        }

        return implode("\n", array_slice($parts, 0, 2));
    }

    /**
     * Limpa os logs do serviço: trunca os arquivos de stdout/stderr (sem
     * removê-los, pois o processo em execução mantém os handles abertos) e
     * apaga o histórico da tabela service_logs.
     *
     * @return array{ok: bool, message: string}
     */
    public function clearLogs(Service $service): array
    {
        foreach ([$service->log_path_out, $service->log_path_err] as $path) {
            if ($path && File::exists($path)) {
                // Truncar preserva o handle aberto pelo processo filho;
                // apagar o arquivo faria o processo perder a saída.
                @file_put_contents($path, '');
            }
        }

        $removed = $service->logs()->delete();

        // Os arquivos voltaram a ter tamanho zero; sem zerar os offsets o
        // ingestor esperaria o log crescer além do tamanho antigo para voltar
        // a capturar linhas.
        $service->update(['log_offset_out' => 0, 'log_offset_err' => 0]);

        $this->logSystem($service, __('services.messages.logs_cleared', ['count' => $removed]));

        return [
            'ok' => true,
            'message' => __('services.messages.logs_cleared', ['count' => $removed]),
        ];
    }

    public function logSystem(Service $service, string $message, string $type = ServiceLog::TYPE_SYSTEM, ?LogLevel $level = null): void
    {
        $service->logs()->create([
            'type' => $type,
            'level' => $level ?? ($type === ServiceLog::TYPE_STDERR ? LogLevel::Error : LogLevel::Info),
            'message' => Str::limit($message, 4000),
        ]);
    }

    /**
     * @return array{ok: bool, path: string, error?: string, wrapper?: bool}
     */
    public function resolveExecutable(string $token, string $dir): array
    {
        $lower = strtolower($token);

        $looksLikePath = str_contains($token, '\\')
            || str_contains($token, '/')
            || str_ends_with($lower, '.exe')
            || str_ends_with($lower, '.cmd')
            || str_ends_with($lower, '.bat')
            || str_ends_with($lower, '.ps1')
            || str_ends_with($lower, '.php');

        if ($looksLikePath) {
            $candidates = [$token, rtrim($dir, '\\/') . '\\' . $token];

            foreach ($candidates as $candidate) {
                if (File::exists($candidate)) {
                    $real = realpath($candidate);

                    return [
                        'ok' => true,
                        'path' => $real,
                        'wrapper' => $this->isCmdWrapper($real),
                    ];
                }
            }

            if ($this->isPhpToken($lower)) {
                return $this->resolvePhpBinary();
            }

            return ['ok' => false, 'error' => __('services.messages.executable_not_found', ['command' => $token])];
        }

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$cmd = Get-Command " . $this->psString($token) . " -CommandType Application -ErrorAction SilentlyContinue;"
            . "if (\$null -eq \$cmd) { \$cmd = Get-Command " . $this->psString($token) . " -ErrorAction SilentlyContinue };"
            . "if (\$null -eq \$cmd) { 'NOT_FOUND' } else { Write-Output \$cmd.Source }";

        $result = $this->runPowerShell($script);

        $output = trim(implode("\n", $result['output']));

        if ($output === '' || str_contains($output, 'NOT_FOUND') || ! str_contains($output, ':')) {
            if ($this->isPhpToken($lower)) {
                return $this->resolvePhpBinary();
            }

            return ['ok' => false, 'error' => __('services.messages.executable_not_found', ['command' => $token])];
        }

        $path = collect(explode("\n", $output))->map(fn (string $line): string => trim($line))->first(
            fn (string $line): bool => str_contains($line, ':') && ! str_contains($line, 'WARNING')
        ) ?? $output;

        return [
            'ok' => true,
            'path' => $path,
            'wrapper' => $this->isCmdWrapper($path),
        ];
    }

    protected function isPhpToken(string $lower): bool
    {
        return $lower === 'php' || $lower === 'php.exe';
    }

    /**
     * @return array{ok: bool, path: string, error?: string, wrapper?: bool}
     */
    protected function resolvePhpBinary(): array
    {
        $binary = PHP_BINARY;

        if ($binary && File::exists($binary)) {
            return [
                'ok' => true,
                'path' => realpath($binary),
                'wrapper' => false,
            ];
        }

        return ['ok' => false, 'error' => __('services.messages.executable_not_found', ['command' => 'php'])];
    }

    public function isCmdWrapper(string $path): bool
    {
        $lower = strtolower($path);

        return str_ends_with($lower, '.cmd')
            || str_ends_with($lower, '.bat')
            || str_ends_with($lower, '.ps1');
    }

    /**
     * @param  list<string>  $args
     */
    protected function buildStartScript(string $exe, array $args, string $dir, string $outPath, string $errPath): string
    {
        $isWrapper = $this->isCmdWrapper($exe);
        $filePath = $isWrapper ? 'cmd.exe' : $exe;
        $argumentList = $this->buildArgumentList($isWrapper, $exe, $args);

        return implode("\n", [
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;",
            "\$ErrorActionPreference = 'Stop';",
            'try {',
            '  $dir = ' . $this->psString($dir) . ';',
            "  if (-not (Test-Path -LiteralPath \$dir)) { throw 'Diretório de trabalho não encontrado: ' + \$dir };",
            '  $out = ' . $this->psString($outPath) . ';',
            '  $err = ' . $this->psString($errPath) . ';',
            '  $p = Start-Process -FilePath ' . $this->psString($filePath) . ' -ArgumentList ' . $argumentList
                . ' -WorkingDirectory $dir -WindowStyle Hidden'
                . ' -RedirectStandardOutput $out -RedirectStandardError $err -PassThru;',
            '  Start-Sleep -Milliseconds 900;',
            "  if (\$p.HasExited) {",
            '    $ec = $p.ExitCode;',
            '    $msg = "";',
            '    $errContent = $null;',
            "    if (Test-Path -LiteralPath \$err) { \$errContent = Get-Content -LiteralPath \$err -Raw -ErrorAction SilentlyContinue };",
            '    if ($errContent) { $msg = ([string]$errContent).Trim() };',
            '    if (-not $msg) {',
            '      $outContent = $null;',
            "      if (Test-Path -LiteralPath \$out) { \$outContent = Get-Content -LiteralPath \$out -Raw -ErrorAction SilentlyContinue };",
            '      if ($outContent) { $msg = ([string]$outContent).Trim() };',
            '    }',
            "    Write-Output ('EXITED|' + \$ec + '|' + \$msg);",
            '    exit 1;',
            '  }',
            "  Write-Output ('PID|' + \$p.Id);",
            '} catch {',
            "  Write-Output ('EXITED|1|' + \$_.Exception.Message);",
            '  exit 1;',
            '}',
        ]);
    }

    /**
     * @param  list<string>  $args
     */
    protected function buildArgumentList(bool $isWrapper, string $exe, array $args): string
    {
        if ($isWrapper) {
            $inner = '"' . $exe . '"' . ($args !== [] ? ' ' . implode(' ', array_map(fn (string $arg): string => $this->psArg($arg), $args)) : '');

            return "@('/c', " . $this->psString($inner) . ')';
        }

        if ($args === []) {
            return '@()';
        }

        $items = implode(', ', array_map(fn (string $arg): string => $this->psString($this->psArg($arg)), $args));

        return "@($items)";
    }

    protected function buildStopScript(int $pid, int $timeout): string
    {
        return implode("\n", [
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;",
            '$timeout = ' . $timeout . ';',
            '$p = Get-Process -Id ' . $pid . ' -ErrorAction SilentlyContinue;',
            "if (\$null -eq \$p) { 'NOT_RUNNING'; exit 0 };",
            "Stop-Process -Id " . $pid . " -ErrorAction SilentlyContinue;",
            '$deadline = (Get-Date).AddSeconds($timeout);',
            'do {',
            '  Start-Sleep -Milliseconds 250;',
            '  $p = Get-Process -Id ' . $pid . ' -ErrorAction SilentlyContinue;',
            '} while ($null -ne $p -and (Get-Date) -lt $deadline);',
            'if ($null -ne $p) {',
            '  & taskkill /PID ' . $pid . ' /T /F 2>&1 | Out-Null;',
            "  'FORCED';",
            '} else {',
            "  'STOPPED';",
            '}',
        ]);
    }

    protected function markStopped(Service $service): void
    {
        $service->update([
            'status' => ServiceStatus::Stopped,
            'pid' => null,
            'started_at' => null,
            'cpu_usage' => null,
            'cpu_seconds_total' => null,
            'memory_kb' => null,
            'uptime_seconds' => null,
            'port_open' => false,
            'exit_code' => null,
            'last_metrics_at' => null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $command): array
    {
        $tokens = [];
        $current = '';
        $inQuote = false;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($char === '"') {
                if ($inQuote && $i + 1 < $length && $command[$i + 1] === '"') {
                    $current .= '"';
                    $i++;
                    continue;
                }

                $inQuote = ! $inQuote;
                continue;
            }

            if ($char === ' ' && ! $inQuote) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    protected function psString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function psArg(string $arg): string
    {
        if ($arg === '') {
            return '""';
        }

        if (preg_match('/[ \t"&|<>^()]/', $arg)) {
            return '"' . str_replace('"', '""', $arg) . '"';
        }

        return $arg;
    }

    protected function tailFile(string $path, int $lines): string
    {
        $size = filesize($path);

        if ($size === false || $size === 0) {
            return '';
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return '';
        }

        $chunkSize = 8192;
        $buffer = '';
        $offset = $size;
        $count = 0;

        while ($offset > 0 && $count <= $lines) {
            $read = min($chunkSize, $offset);
            $offset -= $read;
            fseek($handle, $offset);
            $buffer = fread($handle, $read) . $buffer;
            $count = substr_count($buffer, "\n");

            if ($count > $lines) {
                $pos = strpos($buffer, "\n");

                while ($count > $lines + 1) {
                    $pos = strpos($buffer, "\n", $pos + 1);
                    $count--;
                }

                $buffer = substr($buffer, $pos + 1);

                break;
            }
        }

        fclose($handle);

        return rtrim($buffer, "\r\n");
    }

    /**
     * @param  array<string, string|false>|null  $env  Variáveis extras/sobrescritas para o processo filho.
     *                                                  Valor `false` remove a variável (não repassa a do processo atual).
     *                                                  `null` (padrão) herda o ambiente do processo atual sem alterações.
     * @return array{output: list<string>, exit: int}
     */
    protected function runPowerShell(string $script, int $timeout = 60, ?array $env = null): array
    {
        return $this->powerShell->run($script, $timeout, $env);
    }
}
