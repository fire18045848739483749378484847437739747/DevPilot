<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\Actions\ServiceActions;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Widgets\ServiceLogsWidget;
use App\Filament\Widgets\ServiceLogTableWidget;
use App\Filament\Widgets\ServiceMonitorWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            ServiceActions::open()->record($record),
            ServiceActions::start()->record($record),
            ServiceActions::stop()->record($record),
            ServiceActions::restart()->record($record),
            ServiceActions::kill()->record($record),
            ServiceActions::checkHealth()->record($record),
            ServiceActions::companions()->record($record),
            ServiceActions::projectCommands()->record($record),
            ServiceActions::clearLogs()->record($record),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServiceMonitorWidget::make(['record' => $this->getRecord()]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Console ao vivo (tail do arquivo) e, abaixo, o histórico do banco
            // com busca e filtro por nível.
            ServiceLogsWidget::make(['record' => $this->getRecord()]),
            ServiceLogTableWidget::make(['record' => $this->getRecord()]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
