<?php

namespace App\Filament\Resources\ServiceGroups\Actions;

use App\Models\ServiceGroup;
use App\Services\ServiceOrchestrator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Ações de stack: sobem/derrubam todos os serviços do grupo de uma vez,
 * respeitando a ordem de dependência (`boot_order`).
 */
class ServiceGroupActions
{
    public static function start(): Action
    {
        return static::bulk('startGroup', 'start', Heroicon::OutlinedPlay, 'success')
            ->visible(fn (ServiceGroup $record): bool => $record->services()->count() > $record->runningCount());
    }

    public static function stop(): Action
    {
        return static::bulk('stopGroup', 'stop', Heroicon::OutlinedStop, 'danger')
            ->visible(fn (ServiceGroup $record): bool => $record->runningCount() > 0);
    }

    public static function restart(): Action
    {
        return static::bulk('restartGroup', 'restart', Heroicon::OutlinedArrowPath, 'primary');
    }

    protected static function bulk(string $operation, string $key, Heroicon $icon, string $color): Action
    {
        return Action::make($key . 'Group')
            ->label(__('groups.actions.' . $key))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading(__('groups.actions.' . $key))
            ->modalDescription(__('groups.confirmations.' . $key))
            ->modalSubmitActionLabel(__('groups.actions.' . $key))
            ->action(function (ServiceGroup $record, Action $action) use ($operation): void {
                $summary = app(ServiceOrchestrator::class)->{$operation}($record);

                $notification = Notification::make()->title(__('groups.messages.done', [
                    'group' => $record->name,
                    'ok' => $summary['ok'],
                    'failed' => $summary['failed'],
                    'skipped' => $summary['skipped'],
                ]));

                if ($summary['errors'] !== []) {
                    $notification->body(implode("\n", array_slice($summary['errors'], 0, 5)))->danger()->persistent();
                } elseif ($summary['ok'] === 0) {
                    $notification->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
                $action->getLivewire()->dispatch('$refresh');
            });
    }
}
