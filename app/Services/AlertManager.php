<?php

namespace App\Services;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Alertas do painel (notificações do banco, lidas pelo sino do Filament).
 *
 * Cada tipo de alerta tem um período de silêncio por serviço, senão um serviço
 * em loop de reinício viraria centenas de notificações idênticas.
 */
class AlertManager
{
    public const DOWN = 'down';
    public const RESTART_LIMIT = 'restart_limit';
    public const CPU = 'cpu';
    public const MEMORY = 'memory';
    public const HEALTH = 'health';

    public function serviceDown(Service $service, string $reason): void
    {
        $this->send(
            $service,
            self::DOWN,
            __('services.alerts.down_title', ['name' => $service->name]),
            $reason,
            'danger',
        );
    }

    public function restartLimitReached(Service $service): void
    {
        $this->send(
            $service,
            self::RESTART_LIMIT,
            __('services.alerts.restart_limit_title', ['name' => $service->name]),
            __('services.alerts.restart_limit_body', [
                'count' => $service->restart_count,
                'max' => $service->max_restarts,
            ]),
            'danger',
        );
    }

    public function cpuThreshold(Service $service, float $value, float $limit): void
    {
        $this->send(
            $service,
            self::CPU,
            __('services.alerts.cpu_title', ['name' => $service->name]),
            __('services.alerts.cpu_body', [
                'value' => number_format($value, 1),
                'limit' => number_format($limit, 1),
            ]),
            'warning',
        );
    }

    public function memoryThreshold(Service $service, int $valueMb, int $limitMb): void
    {
        $this->send(
            $service,
            self::MEMORY,
            __('services.alerts.memory_title', ['name' => $service->name]),
            __('services.alerts.memory_body', ['value' => $valueMb, 'limit' => $limitMb]),
            'warning',
        );
    }

    public function healthFailing(Service $service, string $error): void
    {
        $this->send(
            $service,
            self::HEALTH,
            __('services.alerts.health_title', ['name' => $service->name]),
            $error,
            'danger',
        );
    }

    /**
     * A recuperação limpa o silêncio do alerta de health para que uma nova
     * queda volte a notificar imediatamente.
     */
    public function healthRecovered(Service $service): void
    {
        Cache::forget($this->cacheKey($service, self::HEALTH));

        $this->send(
            $service,
            self::HEALTH . '_ok',
            __('services.alerts.health_recovered_title', ['name' => $service->name]),
            __('services.alerts.health_recovered_body'),
            'success',
        );
    }

    /**
     * Libera o silêncio dos alertas de um serviço (usado quando ele volta a
     * ser iniciado manualmente).
     */
    public function reset(Service $service): void
    {
        foreach ([self::DOWN, self::RESTART_LIMIT, self::CPU, self::MEMORY, self::HEALTH, self::HEALTH . '_ok'] as $key) {
            Cache::forget($this->cacheKey($service, $key));
        }
    }

    protected function send(Service $service, string $key, string $title, string $body, string $color): void
    {
        if (! $this->enabledFor($service)) {
            return;
        }

        if (! $this->claim($service, $key)) {
            return;
        }

        $recipients = User::query()->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->status($color);

        $url = $this->serviceUrl($service);

        if ($url !== null) {
            $notification->actions([
                Action::make('view')
                    ->label(__('services.alerts.view_service'))
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        try {
            $notification->sendToDatabase($recipients, isEventDispatched: true);
        } catch (Throwable) {
            // Alerta é efeito colateral: uma falha aqui (tabela de notificações
            // ausente, por exemplo) não pode derrubar o ciclo de supervisão.
        }
    }

    /**
     * O agente roda fora de qualquer painel do Filament, e `getUrl()` depende
     * do painel corrente. Sem contexto, a notificação vai sem o botão.
     */
    protected function serviceUrl(Service $service): ?string
    {
        try {
            return ServiceResource::getUrl('view', ['record' => $service->getKey()], panel: 'admin');
        } catch (Throwable) {
            return null;
        }
    }

    protected function enabledFor(Service $service): bool
    {
        return (bool) config('services_manager.alerts.enabled', true)
            && $service->alerts_enabled;
    }

    /**
     * Marca o alerta como enviado. Devolve `false` se ainda está no período de
     * silêncio.
     */
    protected function claim(Service $service, string $key): bool
    {
        $minutes = max(1, (int) config('services_manager.alerts.cooldown_minutes', 10));

        try {
            return Cache::add($this->cacheKey($service, $key), true, now()->addMinutes($minutes));
        } catch (Throwable) {
            return true;
        }
    }

    protected function cacheKey(Service $service, string $key): string
    {
        return 'service-alert:' . $service->getKey() . ':' . $key;
    }
}
