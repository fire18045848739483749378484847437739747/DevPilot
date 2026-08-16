<?php

namespace App\Services;

use App\Enums\HealthStatus;
use App\Enums\LogLevel;
use App\Models\Service;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Health check HTTP dos serviços.
 *
 * O check de porta responde "tem alguém escutando"; ele não distingue um app
 * de pé de um app que sobe, faz bind e devolve 500 em toda requisição. Aqui a
 * verificação é uma requisição real, comparada com o status esperado.
 */
class HealthChecker
{
    public function __construct(protected ProcessManager $manager, protected AlertManager $alerts)
    {
    }

    /**
     * @param  iterable<Service>  $services
     */
    public function checkMany(iterable $services, bool $force = false): void
    {
        foreach ($services as $service) {
            $this->check($service, $force);
        }
    }

    /**
     * @return HealthStatus|null  `null` quando o serviço não é verificável ou o
     *                            check foi ignorado pelo intervalo.
     */
    public function check(Service $service, bool $force = false): ?HealthStatus
    {
        if (! $service->healthCheckable()) {
            return null;
        }

        if (! $force && ! $this->isDue($service)) {
            return null;
        }

        $url = $service->healthUrl();
        $expected = (int) ($service->health_check_status ?: 200);
        $timeout = max(1, (int) ($service->health_check_timeout ?: 5));

        $code = null;
        $error = null;

        try {
            $response = Http::withOptions(['verify' => false, 'allow_redirects' => false])
                ->timeout($timeout)
                ->connectTimeout($timeout)
                ->get($url);

            $code = $response->status();

            if ($code !== $expected) {
                $error = __('services.messages.health_unexpected_status', [
                    'code' => $code,
                    'expected' => $expected,
                ]);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $error === null
            ? $this->recordSuccess($service, $code)
            : $this->recordFailure($service, $code, $error);
    }

    protected function isDue(Service $service): bool
    {
        $interval = max(5, (int) config('services_manager.health.interval', 30));

        return $service->health_checked_at === null
            || $service->health_checked_at->diffInSeconds(now(), absolute: true) >= $interval;
    }

    protected function recordSuccess(Service $service, ?int $code): HealthStatus
    {
        $recovered = $service->health_status === HealthStatus::Failing;

        $service->update([
            'health_status' => HealthStatus::Ok,
            'health_last_code' => $code,
            'health_error' => null,
            'health_failures' => 0,
            'health_checked_at' => now(),
        ]);

        if ($recovered) {
            $this->manager->logSystem($service, __('services.messages.health_recovered'));
            $this->alerts->healthRecovered($service);
        }

        return HealthStatus::Ok;
    }

    protected function recordFailure(Service $service, ?int $code, string $error): HealthStatus
    {
        $threshold = max(1, (int) config('services_manager.health.failure_threshold', 3));
        $failures = (int) $service->health_failures + 1;
        $status = $failures >= $threshold ? HealthStatus::Failing : HealthStatus::Degraded;
        $wasFailing = $service->health_status === HealthStatus::Failing;

        $service->update([
            'health_status' => $status,
            'health_last_code' => $code,
            'health_error' => $error,
            'health_failures' => $failures,
            'health_checked_at' => now(),
        ]);

        if ($status === HealthStatus::Failing && ! $wasFailing) {
            $this->manager->logSystem(
                $service,
                __('services.messages.health_failing', ['failures' => $failures, 'error' => $error]),
                ServiceLog::TYPE_SYSTEM,
                LogLevel::Error,
            );

            $this->alerts->healthFailing($service, $error);
        }

        return $status;
    }
}
