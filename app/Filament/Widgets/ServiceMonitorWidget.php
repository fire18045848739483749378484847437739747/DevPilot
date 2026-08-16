<?php

namespace App\Filament\Widgets;

use App\Models\Service;
use App\Services\MetricsCollector;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceMonitorWidget extends StatsOverviewWidget
{
    use CanPoll;

    public Service $record;

    protected function getPollingInterval(): ?string
    {
        return '3s';
    }

    protected function getStats(): array
    {
        $record = $this->freshMetrics();

        return [
            Stat::make(__('widgets.pid'), $record->pid ?: '—')
                ->description(__('widgets.pid_desc'))
                ->descriptionIcon(Heroicon::OutlinedFingerPrint)
                ->color($record->pid ? 'primary' : 'gray'),

            Stat::make(
                __('widgets.cpu'),
                $record->cpu_usage !== null ? number_format($record->cpu_usage, 1) . '%' : '—'
            )
                ->description(__('widgets.cpu_desc'))
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color(match (true) {
                    $record->cpu_usage === null => 'gray',
                    $record->cpu_usage >= 80 => 'danger',
                    $record->cpu_usage >= 50 => 'warning',
                    default => 'success',
                }),

            Stat::make(
                __('widgets.memory'),
                $record->memory_kb !== null ? number_format($record->memory_kb / 1024, 1) . ' MB' : '—'
            )
                ->description(__('widgets.memory_desc'))
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color($record->memory_kb !== null ? 'primary' : 'gray'),

            Stat::make(
                __('widgets.uptime'),
                $record->uptime_seconds !== null ? gmdate('H:i:s', $record->uptime_seconds) : '—'
            )
                ->description($record->started_at?->format('d/m/Y H:i:s') ?? __('widgets.uptime_desc'))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($record->uptime_seconds !== null ? 'success' : 'gray'),

            Stat::make(
                __('widgets.port'),
                $record->resolvedPort() ?? '—'
            )
                ->description(match (true) {
                    $record->resolvedPort() === null => __('widgets.port_na'),
                    $record->port_open => __('widgets.port_open'),
                    default => __('widgets.port_closed'),
                })
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->color(match (true) {
                    $record->resolvedPort() === null => 'gray',
                    $record->port_open => 'success',
                    default => 'danger',
                }),
        ];
    }

    /**
     * Coleta métricas sob demanda para que o monitor funcione mesmo sem o
     * agente de supervisão rodando. O MetricsCollector tem throttle próprio,
     * então o polling de 3s não dispara PowerShell a cada requisição.
     */
    protected function freshMetrics(): Service
    {
        if ($this->record->pid) {
            app(MetricsCollector::class)->collect([$this->record]);
            $this->record->refresh();
        }

        return $this->record;
    }
}
