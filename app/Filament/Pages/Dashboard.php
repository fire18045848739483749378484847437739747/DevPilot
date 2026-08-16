<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PortMapWidget;
use App\Filament\Widgets\ResourceUsageChart;
use App\Filament\Widgets\ServiceControlWidget;
use App\Filament\Widgets\ServiceStatsWidget;
use App\Filament\Widgets\ServiceStatusChart;
use App\Services\ServiceOrchestrator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class Dashboard extends \Filament\Pages\Dashboard
{
    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            ServiceStatsWidget::class,
            ServiceControlWidget::class,
            ResourceUsageChart::class,
            ServiceStatusChart::class,
            PortMapWidget::class,
        ];
    }

    /**
     * Ações em lote. Serviços de produção ficam de fora por segurança
     * (ver ServiceOrchestrator::candidates()).
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('startAll')
                ->label(__('dashboard.actions.start_all'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('dashboard.actions.start_all'))
                ->modalDescription(__('dashboard.confirmations.start_all'))
                ->modalSubmitActionLabel(__('dashboard.actions.start_all'))
                ->action(fn () => $this->runBulk('startAll')),

            Action::make('restartAll')
                ->label(__('dashboard.actions.restart_all'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('dashboard.actions.restart_all'))
                ->modalDescription(__('dashboard.confirmations.restart_all'))
                ->modalSubmitActionLabel(__('dashboard.actions.restart_all'))
                ->action(fn () => $this->runBulk('restartAll')),

            Action::make('stopAll')
                ->label(__('dashboard.actions.stop_all'))
                ->icon(Heroicon::OutlinedStop)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('dashboard.actions.stop_all'))
                ->modalDescription(__('dashboard.confirmations.stop_all'))
                ->modalSubmitActionLabel(__('dashboard.actions.stop_all'))
                ->action(fn () => $this->runBulk('stopAll')),
        ];
    }

    /**
     * Executa a operação em lote e notifica o resultado consolidado.
     */
    protected function runBulk(string $operation): void
    {
        $summary = app(ServiceOrchestrator::class)->{$operation}();

        $notification = Notification::make()
            ->title(__('dashboard.messages.bulk_done', [
                'ok' => $summary['ok'],
                'failed' => $summary['failed'],
                'skipped' => $summary['skipped'],
            ]));

        if ($summary['errors'] !== []) {
            $notification
                ->body(implode("\n", array_slice($summary['errors'], 0, 5)))
                ->danger()
                ->persistent();
        } elseif ($summary['ok'] === 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();

        $this->dispatch('$refresh');
    }

    /**
     * Grade responsiva: 1 coluna no celular, 2 no tablet, 5 no desktop.
     *
     * @return int|array<string, int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'lg' => 5,
        ];
    }
}
