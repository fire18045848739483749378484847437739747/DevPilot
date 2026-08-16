<?php

namespace App\Services;

use App\Enums\LogLevel;
use App\Enums\RestartPolicy;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceLog;

class ServiceAgent
{
    public function __construct(
        protected ProcessManager $manager,
        protected MetricsCollector $collector,
        protected LogIngestor $ingestor,
        protected LogRotator $rotator,
        protected AlertManager $alerts,
    ) {
    }

    public function supervise(bool $bootstrap = false): void
    {
        if ($bootstrap) {
            $this->ensureAutoStart();
        }

        $services = Service::query()
            ->whereIn('status', [
                ServiceStatus::Running,
                ServiceStatus::Starting,
                ServiceStatus::Restarting,
            ])
            ->get();

        [$withPid, $withoutPid] = $services->partition(fn (Service $service): bool => (bool) $service->pid);

        foreach ($withoutPid as $service) {
            $this->handleMissingPid($service);
        }

        // Captura o que os processos escreveram desde o ciclo anterior. Roda
        // antes da sondagem para que a saída de um serviço que acabou de morrer
        // (normalmente o motivo da queda) não se perca.
        $this->ingestor->ingestMany($withPid);

        // Uma única sondagem do PowerShell cobre todos os serviços de uma vez.
        foreach ($this->collector->collect($withPid, force: true) as $service) {
            $this->handleUnexpectedExit($service);
        }

        $this->rotator->rotateIfDue();

        AgentState::ping();
    }

    protected function ensureAutoStart(): void
    {
        $services = Service::query()
            ->where('auto_start_on_boot', true)
            ->whereNotIn('status', [
                ServiceStatus::Running,
                ServiceStatus::Starting,
                ServiceStatus::Restarting,
            ])
            ->orderBy('boot_order')
            ->orderBy('name')
            ->get();

        foreach ($services as $service) {
            $this->manager->start($service);

            $delay = (int) ($service->group?->start_delay_seconds ?? 0);

            if ($delay > 0) {
                sleep(min(30, $delay));
            }
        }
    }

    protected function handleMissingPid(Service $service): void
    {
        $service->update([
            'status' => ServiceStatus::Error,
            'last_error' => __('services.messages.pid_missing'),
            'pid' => null,
            'started_at' => null,
        ]);

        $this->manager->logSystem($service, __('services.messages.pid_missing'), ServiceLog::TYPE_STDERR);
        $this->alerts->serviceDown($service, __('services.messages.pid_missing'));
    }

    protected function handleUnexpectedExit(Service $service): void
    {
        $service->refresh();

        if (! $service->status->isActive()) {
            return;
        }

        $service->update([
            'cpu_usage' => null,
            'cpu_seconds_total' => null,
            'memory_kb' => null,
            'uptime_seconds' => null,
            'port_open' => false,
            'pid' => null,
            'started_at' => null,
            'last_metrics_at' => null,
        ]);

        $lastRestart = $service->last_restart_at;
        $limitReached = $service->restart_count >= $service->max_restarts;
        $canRestart = $service->auto_restart
            && $service->restart_policy !== RestartPolicy::Manual
            && ! $limitReached
            && ($lastRestart === null || $lastRestart->diffInSeconds(now()) >= 5);

        if ($canRestart) {
            $count = $service->restart_count + 1;

            $service->update([
                'status' => ServiceStatus::Stopped,
                'restart_count' => $count,
                'last_restart_at' => now(),
            ]);

            $this->manager->logSystem(
                $service,
                __('services.messages.auto_restart', ['count' => $count, 'max' => $service->max_restarts]),
                ServiceLog::TYPE_SYSTEM,
                LogLevel::Warning,
            );

            sleep(2);

            $this->manager->start($service, false);

            return;
        }

        $service->update([
            'status' => ServiceStatus::Error,
            'last_error' => __('services.messages.unexpected_exit'),
        ]);

        $this->manager->logSystem($service, __('services.messages.unexpected_exit'), ServiceLog::TYPE_STDERR);

        // Estourar o limite de reinícios é um evento distinto de uma queda
        // isolada: quem está de olho precisa saber que o serviço desistiu.
        if ($limitReached && $service->auto_restart) {
            $this->alerts->restartLimitReached($service);

            return;
        }

        $this->alerts->serviceDown($service, __('services.messages.unexpected_exit'));
    }
}
