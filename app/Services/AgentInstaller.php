<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Registro do agente de supervisão como serviço do Windows.
 *
 * Sem isto o agente depende de um terminal aberto: fechou o terminal, acabam o
 * reinício automático e o `auto_start_on_boot`. Dois métodos:
 *
 * - `task` (padrão): Agendador de Tarefas do Windows. Nativo, sem dependência.
 *   Com gatilho `logon` a tarefa roda como o usuário atual em modo S4U (sem
 *   senha armazenada e sem janela de console); com `boot`, roda como SYSTEM
 *   desde a inicialização, antes de qualquer login.
 * - `nssm`: registra um serviço de verdade, para quem já usa o NSSM.
 *
 * Ambos exigem elevação. Quando o painel não está elevado (o caso comum, já
 * que ele roda sob o servidor web), `install()` devolve o comando pronto para
 * ser colado em um terminal de administrador.
 */
class AgentInstaller
{
    public const METHOD_TASK = 'task';
    public const METHOD_NSSM = 'nssm';

    public const TRIGGER_LOGON = 'logon';
    public const TRIGGER_BOOT = 'boot';

    public function __construct(protected PowerShell $ps)
    {
    }

    public function taskName(): string
    {
        return (string) config('services_manager.agent.task_name', 'GerenciadorDeProcessos-Agent');
    }

    public function serviceName(): string
    {
        return (string) config('services_manager.agent.service_name', 'GerenciadorProcessosAgent');
    }

    public function nssmPath(): string
    {
        return (string) config('services_manager.agent.nssm_path', 'nssm');
    }

    public function phpBinary(): string
    {
        return PHP_BINARY;
    }

    public function projectPath(): string
    {
        return rtrim(base_path(), '\\/');
    }

    public function agentArguments(): string
    {
        return 'artisan services:agent --interval=' . max(1, (int) config('services_manager.agent.interval', 3));
    }

    public function isElevated(): bool
    {
        return $this->ps->isElevated();
    }

    public function nssmAvailable(): bool
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "if (Get-Command " . $this->ps->str($this->nssmPath()) . " -ErrorAction SilentlyContinue) { 'YES' } else { 'NO' }";

        return str_contains($this->ps->text($script, 20), 'YES');
    }

    /**
     * Situação do registro no Windows.
     *
     * @return array{installed: bool, method: string|null, state: string|null, detail: string|null,
     *               heartbeat: bool, elevated: bool}
     */
    public function status(): array
    {
        $common = [
            'heartbeat' => AgentState::isRunning(),
            'elevated' => $this->isElevated(),
        ];

        $registration = $this->taskStatus() ?? $this->serviceStatus() ?? [
            'installed' => false,
            'method' => null,
            'state' => null,
            'detail' => null,
        ];

        return $registration + $common;
    }

    /**
     * @return array{installed: bool, method: string, state: string, detail: string|null}|null
     */
    protected function taskStatus(): ?array
    {
        $name = $this->ps->str($this->taskName());

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$task = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue;"
            . "if (\$null -eq \$task) { 'ABSENT'; exit 0 };"
            . "\$info = Get-ScheduledTaskInfo -TaskName $name -ErrorAction SilentlyContinue;"
            . "Write-Output ('TASK|' + \$task.State + '|' + \$info.LastRunTime + '|' + \$info.LastTaskResult);";

        $output = $this->ps->text($script, 30);

        if (! preg_match('/^TASK\|([^|]*)\|([^|]*)\|(.*)$/m', $output, $m)) {
            return null;
        }

        return [
            'installed' => true,
            'method' => self::METHOD_TASK,
            'state' => trim($m[1]),
            'detail' => __('agent.status.task_detail', [
                'last_run' => trim($m[2]) ?: '—',
                'result' => trim($m[3]) ?: '—',
            ]),
        ];
    }

    /**
     * @return array{installed: bool, method: string, state: string, detail: string|null}|null
     */
    protected function serviceStatus(): ?array
    {
        $name = $this->ps->str($this->serviceName());

        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$svc = Get-Service -Name $name -ErrorAction SilentlyContinue;"
            . "if (\$null -eq \$svc) { 'ABSENT'; exit 0 };"
            . "Write-Output ('SERVICE|' + \$svc.Status + '|' + \$svc.StartType);";

        $output = $this->ps->text($script, 30);

        if (! preg_match('/^SERVICE\|([^|]*)\|(.*)$/m', $output, $m)) {
            return null;
        }

        return [
            'installed' => true,
            'method' => self::METHOD_NSSM,
            'state' => trim($m[1]),
            'detail' => __('agent.status.service_detail', ['start_type' => trim($m[2]) ?: '—']),
        ];
    }

    /**
     * Comando equivalente, para o usuário rodar em um terminal de administrador
     * quando o painel não estiver elevado.
     */
    public function manualCommand(string $method = self::METHOD_TASK, string $trigger = self::TRIGGER_LOGON): string
    {
        return 'cd "' . $this->projectPath() . '" && php artisan agent:install'
            . ' --method=' . $method
            . ' --trigger=' . $trigger;
    }

    /**
     * @return array{ok: bool, message: string, command?: string}
     */
    public function install(string $method = self::METHOD_TASK, string $trigger = self::TRIGGER_LOGON): array
    {
        if (! $this->isElevated()) {
            return [
                'ok' => false,
                'message' => __('agent.messages.needs_elevation'),
                'command' => $this->manualCommand($method, $trigger),
            ];
        }

        return $method === self::METHOD_NSSM
            ? $this->installService()
            : $this->installTask($trigger);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function installTask(string $trigger): array
    {
        $name = $this->ps->str($this->taskName());
        $php = $this->ps->str($this->phpBinary());
        $dir = $this->ps->str($this->projectPath());
        $args = $this->ps->str($this->agentArguments());

        // S4U roda sem sessão interativa: não mostra janela de console e não
        // exige senha armazenada. SYSTEM é o equivalente para o gatilho de boot.
        $principal = $trigger === self::TRIGGER_BOOT
            ? "\$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest;"
            : "\$principal = New-ScheduledTaskPrincipal -UserId \"\$env:USERDOMAIN\\\$env:USERNAME\" -LogonType S4U -RunLevel Highest;";

        $triggerScript = $trigger === self::TRIGGER_BOOT
            ? "\$trigger = New-ScheduledTaskTrigger -AtStartup;"
            : "\$trigger = New-ScheduledTaskTrigger -AtLogOn;";

        $script = implode("\n", [
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;",
            "\$ErrorActionPreference = 'Stop';",
            'try {',
            "  \$action = New-ScheduledTaskAction -Execute $php -Argument $args -WorkingDirectory $dir;",
            '  ' . $triggerScript,
            '  ' . $principal,
            '  $settings = New-ScheduledTaskSettingsSet'
                . ' -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries'
                . ' -ExecutionTimeLimit ([TimeSpan]::Zero)'
                . ' -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)'
                . ' -MultipleInstances IgnoreNew;',
            "  Register-ScheduledTask -TaskName $name -Action \$action -Trigger \$trigger"
                . ' -Principal $principal -Settings $settings -Force | Out-Null;',
            "  Start-ScheduledTask -TaskName $name;",
            "  'OK';",
            '} catch {',
            "  Write-Output ('ERR|' + \$_.Exception.Message);",
            '}',
        ]);

        $output = $this->ps->text($script, 90);

        if (str_contains($output, 'OK')) {
            return ['ok' => true, 'message' => __('agent.messages.installed_task', ['name' => $this->taskName()])];
        }

        return ['ok' => false, 'message' => $this->errorFrom($output)];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function installService(): array
    {
        if (! $this->nssmAvailable()) {
            return ['ok' => false, 'message' => __('agent.messages.nssm_missing', ['path' => $this->nssmPath()])];
        }

        $nssm = $this->ps->str($this->nssmPath());
        $name = $this->ps->str($this->serviceName());
        $php = $this->ps->str($this->phpBinary());
        $dir = $this->ps->str($this->projectPath());
        $args = $this->ps->str($this->agentArguments());
        $out = $this->ps->str(storage_path('logs/agent-service.out.log'));
        $err = $this->ps->str(storage_path('logs/agent-service.err.log'));

        $script = implode("\n", [
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;",
            "\$ErrorActionPreference = 'Continue';",
            "\$existing = Get-Service -Name $name -ErrorAction SilentlyContinue;",
            "if (\$existing) { & $nssm stop $name confirm | Out-Null; & $nssm remove $name confirm | Out-Null };",
            "& $nssm install $name $php $args | Out-Null;",
            "& $nssm set $name AppDirectory $dir | Out-Null;",
            "& $nssm set $name AppStdout $out | Out-Null;",
            "& $nssm set $name AppStderr $err | Out-Null;",
            "& $nssm set $name Start SERVICE_AUTO_START | Out-Null;",
            "& $nssm set $name AppExit Default Restart | Out-Null;",
            "& $nssm start $name | Out-Null;",
            "\$svc = Get-Service -Name $name -ErrorAction SilentlyContinue;",
            "if (\$svc) { 'OK' } else { 'ERR|Serviço não encontrado após a instalação.' };",
        ]);

        $output = $this->ps->text($script, 90);

        if (str_contains($output, 'OK')) {
            return ['ok' => true, 'message' => __('agent.messages.installed_service', ['name' => $this->serviceName()])];
        }

        return ['ok' => false, 'message' => $this->errorFrom($output)];
    }

    /**
     * @return array{ok: bool, message: string, command?: string}
     */
    public function uninstall(): array
    {
        if (! $this->isElevated()) {
            return [
                'ok' => false,
                'message' => __('agent.messages.needs_elevation'),
                'command' => 'cd "' . $this->projectPath() . '" && php artisan agent:uninstall',
            ];
        }

        $taskName = $this->ps->str($this->taskName());
        $serviceName = $this->ps->str($this->serviceName());
        $nssm = $this->ps->str($this->nssmPath());

        $script = implode("\n", [
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;",
            "\$removed = @();",
            "if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {",
            "  Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue;",
            "  Unregister-ScheduledTask -TaskName $taskName -Confirm:\$false;",
            "  \$removed += 'task';",
            '}',
            "if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {",
            "  & $nssm stop $serviceName confirm | Out-Null;",
            "  & $nssm remove $serviceName confirm | Out-Null;",
            "  \$removed += 'service';",
            '}',
            "Write-Output ('REMOVED|' + (\$removed -join ','));",
        ]);

        $output = $this->ps->text($script, 90);

        if (! preg_match('/^REMOVED\|(.*)$/m', $output, $m)) {
            return ['ok' => false, 'message' => $this->errorFrom($output)];
        }

        $removed = array_values(array_filter(explode(',', trim($m[1]))));

        return $removed === []
            ? ['ok' => false, 'message' => __('agent.messages.not_installed')]
            : ['ok' => true, 'message' => __('agent.messages.uninstalled')];
    }

    /**
     * @return array{ok: bool, message: string, command?: string}
     */
    public function start(): array
    {
        return $this->control(true);
    }

    /**
     * @return array{ok: bool, message: string, command?: string}
     */
    public function stop(): array
    {
        return $this->control(false);
    }

    /**
     * @return array{ok: bool, message: string, command?: string}
     */
    protected function control(bool $start): array
    {
        $status = $this->status();

        if (! $status['installed']) {
            return ['ok' => false, 'message' => __('agent.messages.not_installed')];
        }

        if (! $this->isElevated()) {
            return [
                'ok' => false,
                'message' => __('agent.messages.needs_elevation'),
                'command' => 'cd "' . $this->projectPath() . '" && php artisan agent:control ' . ($start ? 'start' : 'stop'),
            ];
        }

        $verb = $start ? 'Start' : 'Stop';

        $script = $status['method'] === self::METHOD_NSSM
            ? "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
                . "& " . $this->ps->str($this->nssmPath()) . " " . Str::lower($verb) . " " . $this->ps->str($this->serviceName()) . " | Out-Null; 'OK';"
            : "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
                . "{$verb}-ScheduledTask -TaskName " . $this->ps->str($this->taskName()) . " -ErrorAction SilentlyContinue | Out-Null; 'OK';";

        $output = $this->ps->text($script, 60);

        if (! str_contains($output, 'OK')) {
            return ['ok' => false, 'message' => $this->errorFrom($output)];
        }

        return [
            'ok' => true,
            'message' => $start ? __('agent.messages.started') : __('agent.messages.stopped'),
        ];
    }

    protected function errorFrom(string $output): string
    {
        if (preg_match('/^ERR\|(.*)$/ms', $output, $m)) {
            return trim($m[1]);
        }

        return trim($output) ?: __('agent.messages.unknown_error');
    }
}
