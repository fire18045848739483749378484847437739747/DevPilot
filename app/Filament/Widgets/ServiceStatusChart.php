<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\CanPoll;

/**
 * Distribuição dos serviços por status.
 */
class ServiceStatusChart extends ChartWidget
{
    use CanPoll;

    protected ?string $maxHeight = '260px';

    protected function getPollingInterval(): ?string
    {
        return '10s';
    }

    public function getHeading(): ?string
    {
        return __('widgets.chart_status');
    }

    public function getDescription(): ?string
    {
        return __('widgets.chart_status_desc');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Service::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $data = [];
        $colors = [];

        $palette = [
            ServiceStatus::Running->value => '#22c55e',
            ServiceStatus::Stopped->value => '#71717a',
            ServiceStatus::Error->value => '#ef4444',
            ServiceStatus::Starting->value => '#f59e0b',
            ServiceStatus::Restarting->value => '#06b6d4',
        ];

        foreach (ServiceStatus::cases() as $status) {
            $total = (int) ($counts[$status->value] ?? 0);

            if ($total === 0) {
                continue;
            }

            $labels[] = $status->getLabel();
            $data[] = $total;
            $colors[] = $palette[$status->value] ?? '#71717a';
        }

        return [
            'datasets' => [[
                'label' => __('widgets.chart_status'),
                'data' => $data,
                'backgroundColor' => $colors,
                'borderColor' => '#0f1613',
                'borderWidth' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'padding' => 14],
                ],
            ],
        ];
    }

    public static function canView(): bool
    {
        return Service::query()->exists();
    }
}
