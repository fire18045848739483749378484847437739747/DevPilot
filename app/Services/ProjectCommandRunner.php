<?php

namespace App\Services;

use App\Enums\LogLevel;
use App\Models\Service;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Execução dos comandos do catálogo na pasta de um projeto.
 *
 * Ao contrário dos serviços, aqui a execução é **síncrona**: são comandos de
 * uma tacada só (`migrate`, `optimize:clear`, `npm run build`) e o usuário quer
 * ver a saída. O processo herda o ambiente já limpo pelo `HostEnvironment` —
 * sem isso um `php artisan migrate` na pasta do projeto rodaria contra o banco
 * do Gerenciador, que é o bug documentado na seção 10.1 do SISTEMA.md.
 */
class ProjectCommandRunner
{
    public function __construct(
        protected ProcessManager $manager,
        protected ProjectCommandCatalog $catalog,
        protected DirectoryBrowser $browser,
    ) {
    }

    /**
     * @return array{ok: bool, command: string, output: string, exit_code: int|null, duration: float}
     */
    public function run(string $key, ?string $directory): array
    {
        $item = $this->catalog->find($key);

        if ($item === null) {
            return $this->failure('', __('commands.messages.unknown', ['key' => $key]));
        }

        $directory = trim((string) $directory);

        // O runner é chamado a partir da web: a pasta precisa estar dentro das
        // raízes configuradas, senão o painel viraria um shell no disco todo.
        if ($directory === '' || ! File::isDirectory($directory) || ! $this->browser->isAllowed($directory)) {
            return $this->failure($item['command'], __('commands.messages.invalid_directory'));
        }

        if (! $this->catalog->isAvailable($item, $directory)) {
            return $this->failure($item['command'], __('commands.messages.not_applicable', [
                'requires' => __('commands.requires.' . $item['requires']),
            ]));
        }

        $tokens = $this->manager->tokenize($item['command']);

        if ($tokens === []) {
            return $this->failure($item['command'], __('commands.messages.empty'));
        }

        $resolution = $this->manager->resolveExecutable($tokens[0], $directory);

        if (! $resolution['ok']) {
            return $this->failure($item['command'], $resolution['error']);
        }

        return $this->execute($item, $directory, $resolution['path'], array_slice($tokens, 1));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $args
     * @return array{ok: bool, command: string, output: string, exit_code: int|null, duration: float}
     */
    protected function execute(array $item, string $directory, string $executable, array $args): array
    {
        // `composer` e `npm` no Windows são wrappers .bat/.cmd, que o
        // CreateProcess não executa direto — precisam passar pelo cmd.exe.
        $commandLine = $this->manager->isCmdWrapper($executable)
            ? array_merge(['cmd.exe', '/c', $executable], $args)
            : array_merge([$executable], $args);

        $process = new Process($commandLine, $directory, HostEnvironment::forChild());
        $process->setTimeout((float) $item['timeout']);

        $startedAt = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } catch (Throwable $e) {
            return $this->failure($item['command'], $e->getMessage());
        }

        $duration = round(microtime(true) - $startedAt, 2);
        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

        if ($timedOut) {
            $output = trim($output . "\n" . __('commands.messages.timed_out', ['seconds' => $item['timeout']]));
        }

        $ok = ! $timedOut && $process->getExitCode() === 0;

        $result = [
            'ok' => $ok,
            'command' => $item['command'],
            'output' => $output !== '' ? $output : __('commands.messages.no_output'),
            'exit_code' => $timedOut ? null : $process->getExitCode(),
            'duration' => $duration,
        ];

        $this->record($item, $directory, $result);

        return $result;
    }

    /**
     * Deixa rastro nos serviços daquela pasta: quem olhar o histórico de logs
     * depois entende por que o comportamento mudou do nada.
     *
     * @param  array<string, mixed>  $item
     * @param  array{ok: bool, command: string, output: string, exit_code: int|null, duration: float}  $result
     */
    protected function record(array $item, string $directory, array $result): void
    {
        $services = Service::query()->where('working_directory', $directory)->get();

        if ($services->isEmpty()) {
            return;
        }

        $message = __('commands.messages.executed', [
            'command' => $item['command'],
            'status' => $result['ok'] ? __('commands.messages.success') : __('commands.messages.failure'),
            'duration' => $result['duration'],
        ]);

        foreach ($services as $service) {
            $this->manager->logSystem(
                $service,
                $message . "\n" . Str::limit($result['output'], 1000),
                ServiceLog::TYPE_SYSTEM,
                $result['ok'] ? LogLevel::Info : LogLevel::Error,
            );
        }
    }

    /**
     * @return array{ok: bool, command: string, output: string, exit_code: int|null, duration: float}
     */
    protected function failure(string $command, string $message): array
    {
        return [
            'ok' => false,
            'command' => $command,
            'output' => $message,
            'exit_code' => null,
            'duration' => 0.0,
        ];
    }
}
