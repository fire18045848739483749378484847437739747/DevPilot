<?php

namespace App\Filament\Resources\Services\Pages;

use App\Enums\ServiceStatus;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Widgets\ServiceStatsWidget;
use App\Models\Service;
use App\Services\LogIngestor;
use App\Services\MetricsCollector;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServiceStatsWidget::class,
        ];
    }

    /**
     * Atualiza as métricas de todos os serviços ativos em uma única sondagem
     * antes de montar a tabela, para que a listagem mostre dados reais mesmo
     * sem o agente de supervisão em execução.
     */
    protected function getTableQuery(): ?Builder
    {
        $this->syncMetrics();

        return parent::getTableQuery();
    }

    protected function syncMetrics(): void
    {
        $services = Service::query()
            ->whereNotNull('pid')
            ->whereIn('status', [
                ServiceStatus::Running,
                ServiceStatus::Starting,
                ServiceStatus::Restarting,
            ])
            ->get();

        if ($services->isEmpty()) {
            return;
        }

        // Captura o stdout/stderr acumulado desde a última visita. É só leitura
        // de arquivo, então cabe no ciclo de renderização da listagem.
        app(LogIngestor::class)->ingestMany($services);

        app(MetricsCollector::class)->collect($services);
    }
}
