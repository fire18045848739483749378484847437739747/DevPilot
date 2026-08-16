<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceMetric;
use Illuminate\Support\Collection;

/**
 * Coleta métricas (CPU/memória/porta) de um conjunto de serviços.
 *
 * Fonte única de verdade usada tanto pelo agente de supervisão quanto pelo
 * painel web. Antes as métricas só eram atualizadas pelo comando
 * `services:agent`; se ele não estivesse rodando, o painel mostrava tudo "—".
 */
class MetricsCollector
{
    /**
     * Intervalo mínimo entre coletas do mesmo serviço, em segundos.
     * Evita disparar PowerShell a cada polling de widget.
     */
    public const THROTTLE_SECONDS = 2;

    public function __construct(
        protected ProcessManager $manager,
        protected AlertManager $alerts,
        protected HealthChecker $health,
    ) {
    }

    /**
     * Atualiza as métricas dos serviços informados.
     *
     * @param  iterable<Service>  $services
     * @param  bool  $force  Ignora o throttle (usado pelo agente).
     * @return Collection<int, Service>  Serviços cujo processo não está mais vivo.
     */
    public function collect(iterable $services, bool $force = false): Collection
    {
        $services = collect($services);

        $pending = $services->filter(function (Service $service) use ($force): bool {
            if (! $service->pid) {
                return false;
            }

            if ($force || $service->last_metrics_at === null) {
                return true;
            }

            return $service->last_metrics_at->diffInSeconds(now()) >= self::THROTTLE_SECONDS;
        });

        if ($pending->isEmpty()) {
            return collect();
        }

        $probe = $this->manager->probe($pending->pluck('pid')->all());
        $ports = $probe['ports'];
        $dead = collect();

        foreach ($pending as $service) {
            $sample = $probe['procs'][$service->pid] ?? null;

            if ($sample === null) {
                $dead->push($service);

                continue;
            }

            $this->apply($service, $sample, $ports);
        }

        return $dead;
    }

    /**
     * @param  array{cpu_seconds: float, memory_kb: int}  $sample
     * @param  list<int>  $listeningPorts
     */
    protected function apply(Service $service, array $sample, array $listeningPorts): void
    {
        $cpu = $this->computeCpuPercent($service, $sample['cpu_seconds']);
        $portOpen = $service->port ? in_array($service->port, $listeningPorts, true) : null;

        $service->update([
            'cpu_usage' => $cpu,
            'cpu_seconds_total' => $sample['cpu_seconds'],
            'memory_kb' => $sample['memory_kb'],
            'uptime_seconds' => $service->started_at
                ? (int) max(0, $service->started_at->diffInSeconds(now()))
                : null,
            'port_open' => $portOpen,
            'last_metrics_at' => now(),
        ]);

        ServiceMetric::record($service);

        $this->evaluateThresholds($service);

        // O health check tem intervalo próprio, bem maior que o das métricas.
        $this->health->check($service);
    }

    /**
     * Dispara alerta quando o serviço passa dos limites configurados de CPU ou
     * memória. Os limites são por serviço; nulos desligam a checagem.
     */
    protected function evaluateThresholds(Service $service): void
    {
        $cpuLimit = $service->alert_cpu_threshold;

        if ($cpuLimit !== null && $service->cpu_usage !== null && $service->cpu_usage >= $cpuLimit) {
            $this->alerts->cpuThreshold($service, $service->cpu_usage, $cpuLimit);
        }

        $memoryLimit = $service->alert_memory_threshold_mb;

        if ($memoryLimit !== null && $service->memory_kb !== null) {
            $memoryMb = (int) round($service->memory_kb / 1024);

            if ($memoryMb >= $memoryLimit) {
                $this->alerts->memoryThreshold($service, $memoryMb, $memoryLimit);
            }
        }
    }

    /**
     * CPU% = variação de segundos de CPU / variação de tempo real.
     *
     * Usa os valores persistidos da coleta anterior, então funciona mesmo
     * quando cada coleta acontece em um processo PHP diferente (painel web).
     */
    protected function computeCpuPercent(Service $service, float $cpuSeconds): ?float
    {
        $previousCpu = $service->cpu_seconds_total;
        $previousAt = $service->last_metrics_at;

        if ($previousCpu === null || $previousAt === null) {
            return null;
        }

        $deltaTime = $previousAt->diffInRealSeconds(now(), absolute: true);
        $deltaCpu = $cpuSeconds - $previousCpu;

        if ($deltaTime <= 0 || $deltaCpu < 0) {
            return null;
        }

        return round(min(100, ($deltaCpu / $deltaTime) * 100), 1);
    }
}
