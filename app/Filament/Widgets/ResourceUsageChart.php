<?php

namespace App\Filament\Widgets;

use App\Models\Service;
use App\Models\ServiceMetric;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\CanPoll;
use Illuminate\Support\Carbon;

/**
 * Histórico de CPU e memória dos serviços em execução, a partir das amostras
 * gravadas em `service_metrics`.
 */
class ResourceUsageChart extends ChartWidget
{
    use CanPoll;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30m';

    protected function getPollingInterval(): ?string
    {
        return '15s';
    }

    public function getHeading(): ?string
    {
        return __('widgets.chart_resources');
    }

    public function getDescription(): ?string
    {
        return __('widgets.chart_resources_desc');
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '15m' => __('widgets.range_15m'),
            '30m' => __('widgets.range_30m'),
            '3h' => __('widgets.range_3h'),
            '24h' => __('widgets.range_24h'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $minutes = match ($this->filter) {
            '15m' => 15,
            '3h' => 180,
            '24h' => 1440,
            default => 30,
        };

        $since = now()->subMinutes($minutes);

        $samples = ServiceMetric::query()
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get();

        if ($samples->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        // Agrupa em intervalos regulares para não poluir o eixo X.
        $bucketSeconds = max(30, (int) ceil($minutes * 60 / 40));

        $buckets = $samples->groupBy(
            fn (ServiceMetric $metric): int => (int) floor($metric->recorded_at->timestamp / $bucketSeconds)
        );

        $labels = [];
        $cpu = [];
        $memory = [];

        foreach ($buckets as $bucket => $group) {
            $labels[] = Carbon::createFromTimestamp($bucket * $bucketSeconds)->format('H:i');
            $cpu[] = round((float) $group->avg('cpu_usage'), 1);
            $memory[] = round((float) $group->sum('memory_kb') / 1024, 1);
        }

        return [
            'datasets' => [
                [
                    'label' => __('widgets.chart_cpu_label'),
                    'data' => $cpu,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('widgets.chart_memory_label'),
                    'data' => $memory,
                    'borderColor' => '#06b6d4',
                    'backgroundColor' => 'rgba(6, 182, 212, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['labels' => ['usePointStyle' => true]],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'CPU %'],
                ],
                'y1' => [
                    'type' => 'linear',
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'MB'],
                ],
            ],
        ];
    }

    public static function canView(): bool
    {
        return Service::query()->exists();
    }
}
