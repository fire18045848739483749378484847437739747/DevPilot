<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Support\Collection;

/**
 * Operações em lote sobre vários serviços (iniciar/parar/reiniciar todos).
 *
 * Cada operação devolve um resumo com quantos tiveram sucesso, quantos
 * falharam e quantos foram ignorados, para alimentar a notificação do painel.
 */
class ServiceOrchestrator
{
    public function __construct(protected ProcessManager $manager)
    {
    }

    /**
     * Inicia todos os serviços que não estejam rodando.
     *
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function startAll(): array
    {
        return $this->run(
            $this->candidates(),
            fn (Service $service): bool => ! $service->isRunning() && ! $service->isPending(),
            fn (Service $service): array => $this->manager->start($service),
        );
    }

    /**
     * Para todos os serviços em execução.
     *
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function stopAll(): array
    {
        return $this->run(
            $this->candidates(),
            fn (Service $service): bool => $service->isRunning(),
            fn (Service $service): array => $this->manager->stop($service),
        );
    }

    /**
     * Reinicia todos os serviços que estejam rodando.
     *
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function restartAll(): array
    {
        return $this->run(
            $this->candidates(),
            fn (Service $service): bool => $service->isRunning(),
            fn (Service $service): array => $this->manager->restart($service),
        );
    }

    /**
     * Sobe o grupo inteiro, na ordem de `boot_order`, com a pausa configurada
     * entre um serviço e o próximo (o dependente precisa que a dependência já
     * esteja de pé).
     *
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function startGroup(ServiceGroup $group): array
    {
        return $this->run(
            $group->orderedServices()->get(),
            fn (Service $service): bool => ! $service->isRunning() && ! $service->isPending(),
            fn (Service $service): array => $this->manager->start($service),
            delaySeconds: (int) $group->start_delay_seconds,
        );
    }

    /**
     * Derruba o grupo na ordem inversa da subida.
     *
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function stopGroup(ServiceGroup $group): array
    {
        return $this->run(
            $group->orderedServices()->get()->reverse(),
            fn (Service $service): bool => $service->isRunning(),
            fn (Service $service): array => $this->manager->stop($service),
        );
    }

    /**
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    public function restartGroup(ServiceGroup $group): array
    {
        $this->stopGroup($group);

        return $this->startGroup($group);
    }

    /**
     * Serviços elegíveis para operações em lote.
     *
     * Serviços de produção ficam de fora por segurança: derrubar produção sem
     * querer, a partir de um botão "parar todos", seria caro demais.
     *
     * @return Collection<int, Service>
     */
    public function candidates(): Collection
    {
        return Service::query()
            ->where('environment', '!=', \App\Enums\ServiceEnvironment::Production)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  callable(Service): bool  $shouldRun
     * @param  callable(Service): array{ok: bool, message: string}  $operation
     * @param  int  $delaySeconds  Pausa entre operações bem-sucedidas.
     * @return array{ok: int, failed: int, skipped: int, errors: list<string>}
     */
    protected function run(Collection $services, callable $shouldRun, callable $operation, int $delaySeconds = 0): array
    {
        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $remaining = $services->count();

        foreach ($services as $service) {
            $service->refresh();
            $remaining--;

            if (! $shouldRun($service)) {
                $skipped++;

                continue;
            }

            $result = $operation($service);

            if ($result['ok']) {
                $ok++;

                // A pausa só existe para dar tempo à dependência; depois do
                // último serviço ela só faria a requisição demorar mais.
                if ($delaySeconds > 0 && $remaining > 0) {
                    sleep(min(30, $delaySeconds));
                }

                continue;
            }

            $failed++;
            $errors[] = $service->name . ': ' . $result['message'];
        }

        return ['ok' => $ok, 'failed' => $failed, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Quantidade de serviços rodando (usado para habilitar/desabilitar botões).
     */
    public function runningCount(): int
    {
        return Service::query()->where('status', ServiceStatus::Running)->count();
    }
}
