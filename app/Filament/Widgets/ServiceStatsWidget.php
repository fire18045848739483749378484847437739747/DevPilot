<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\AgentState;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceStatsWidget extends StatsOverviewWidget
{
    use CanPoll;

    protected function getPollingInterval(): ?string
    {
        return '5s';
    }

    protected function getStats(): array
    {
        $total = Service::count();
        $running = Service::where('status', ServiceStatus::Running)->count();
        $errored = Service::where('status', ServiceStatus::Error)->count();
        $stopped = Service::where('status', ServiceStatus::Stopped)->count();
        $agentRunning = AgentState::isRunning();

        return [
            Stat::make(__('widgets.total_services'), $total)
                ->description(__('widgets.total_services_desc'))
                ->descriptionIcon(Heroicon::OutlinedServer)
                ->color('primary'),

            Stat::make(__('widgets.running_services'), $running)
                ->description(__('widgets.running_services_desc'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make(__('widgets.error_services'), $errored)
                ->description(__('widgets.error_services_desc'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),

            Stat::make(__('widgets.stopped_services'), $stopped)
                ->description(__('widgets.stopped_services_desc'))
                ->descriptionIcon(Heroicon::OutlinedStopCircle)
                ->color('gray'),

            Stat::make(
                __('widgets.agent_status'),
                $agentRunning ? __('widgets.agent_running') : __('widgets.agent_stopped')
            )
                ->description($agentRunning ? __('widgets.agent_running_desc') : __('widgets.agent_stopped_desc'))
                ->descriptionIcon($agentRunning ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                ->color($agentRunning ? 'success' : 'danger'),
        ];
    }
}
