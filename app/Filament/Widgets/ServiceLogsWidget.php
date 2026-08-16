<?php

namespace App\Filament\Widgets;

use App\Models\Service;
use App\Services\ProcessManager;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

class ServiceLogsWidget extends Widget
{
    use CanPoll;

    protected string $view = 'filament.widgets.service-logs-widget';

    protected function getPollingInterval(): ?string
    {
        return '3s';
    }

    public Service $record;

    public function getLogs(): string
    {
        return app(ProcessManager::class)->tailLog($this->record, 300);
    }
}
