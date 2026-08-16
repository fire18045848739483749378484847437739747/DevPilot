<?php

namespace App\Filament\Resources\Services\Actions;

use App\Enums\HealthStatus;
use App\Filament\Pages\ProjectCommandsPage;
use App\Models\Service;
use App\Services\CompanionServiceFactory;
use App\Services\DirectoryBrowser;
use App\Services\HealthChecker;
use App\Services\ProcessManager;
use App\Services\ProjectCommandCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ServiceActions
{
    public static function start(): Action
    {
        return Action::make('start')
            ->label(__('services.actions.start'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->visible(fn (Service $record): bool => ! $record->isRunning() && ! $record->isPending())
            ->action(function (Service $record, Action $action): void {
                $result = app(ProcessManager::class)->start($record);

                static::notify($result['ok'], $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    public static function stop(): Action
    {
        return Action::make('stop')
            ->label(__('services.actions.stop'))
            ->icon(Heroicon::OutlinedPause)
            ->color('warning')
            ->visible(fn (Service $record): bool => $record->isRunning())
            ->requiresConfirmation()
            ->modalHeading(__('services.actions.stop'))
            ->modalDescription(__('services.confirmations.stop'))
            ->modalSubmitActionLabel(__('services.actions.stop'))
            ->action(function (Service $record, Action $action): void {
                $result = app(ProcessManager::class)->stop($record);

                static::notify($result['ok'], $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    public static function restart(): Action
    {
        return Action::make('restart')
            ->label(__('services.actions.restart'))
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->color('primary')
            ->visible(fn (Service $record): bool => ! $record->isPending())
            ->requiresConfirmation()
            ->modalHeading(__('services.actions.restart'))
            ->modalDescription(__('services.confirmations.restart'))
            ->modalSubmitActionLabel(__('services.actions.restart'))
            ->action(function (Service $record, Action $action): void {
                $result = app(ProcessManager::class)->restart($record);

                static::notify($result['ok'], $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    public static function kill(): Action
    {
        return Action::make('kill')
            ->label(__('services.actions.kill'))
            ->icon(Heroicon::OutlinedBolt)
            ->color('danger')
            ->visible(fn (Service $record): bool => $record->isRunning())
            ->requiresConfirmation()
            ->modalHeading(__('services.actions.kill'))
            ->modalDescription(__('services.confirmations.kill'))
            ->modalSubmitActionLabel(__('services.actions.kill'))
            ->action(function (Service $record, Action $action): void {
                $result = app(ProcessManager::class)->kill($record);

                static::notify($result['ok'], $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    public static function logs(): Action
    {
        return Action::make('logs')
            ->label(__('services.actions.logs'))
            ->icon(Heroicon::OutlinedCommandLine)
            ->color('gray')
            ->modalHeading(__('services.actions.logs'))
            ->modalSubmitAction(false)
            ->schema([
                Textarea::make('logs')
                    ->label(__('services.fields.logs'))
                    ->disabled()
                    ->rows(20)
                    ->default(fn (Service $record): string => app(ProcessManager::class)->tailLog($record)),
            ]);
    }

    /**
     * Abre a URL de acesso do serviço em uma nova aba.
     */
    public static function open(): Action
    {
        return Action::make('open')
            ->label(__('services.actions.open'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('info')
            ->visible(fn (Service $record): bool => $record->accessUrl() !== null)
            ->url(fn (Service $record): ?string => $record->accessUrl(), shouldOpenInNewTab: true);
    }

    /**
     * Limpa os logs do serviço: trunca os arquivos de stdout/stderr e apaga
     * os registros da tabela service_logs.
     */
    public static function clearLogs(): Action
    {
        return Action::make('clearLogs')
            ->label(__('services.actions.clear_logs'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('services.actions.clear_logs'))
            ->modalDescription(__('services.confirmations.clear_logs'))
            ->modalSubmitActionLabel(__('services.actions.clear_logs'))
            ->action(function (Service $record, Action $action): void {
                $result = app(ProcessManager::class)->clearLogs($record);

                static::notify($result['ok'], $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    /**
     * Abre a página de comandos já apontada para a pasta deste serviço.
     */
    public static function projectCommands(): Action
    {
        return Action::make('projectCommands')
            ->label(__('services.actions.project_commands'))
            ->icon(Heroicon::OutlinedCommandLine)
            ->color('gray')
            ->visible(
                fn (Service $record): bool => filled($record->working_directory)
                    && app(ProjectCommandCatalog::class)->capabilities($record->working_directory) !== []
            )
            ->url(fn (Service $record): string => ProjectCommandsPage::getUrl(
                ['directory' => $record->working_directory],
            ));
    }

    /**
     * Roda o health check imediatamente, sem esperar o intervalo.
     */
    public static function checkHealth(): Action
    {
        return Action::make('checkHealth')
            ->label(__('services.actions.check_health'))
            ->icon(Heroicon::OutlinedHeart)
            ->color('info')
            ->visible(fn (Service $record): bool => $record->healthCheckable())
            ->action(function (Service $record, Action $action): void {
                $status = app(HealthChecker::class)->check($record, force: true);
                $record->refresh();

                static::notify(
                    $status === HealthStatus::Ok,
                    __('services.messages.health_result', [
                        'status' => $status?->getLabel() ?? '—',
                        'code' => $record->health_last_code ?? '—',
                    ]),
                );

                $action->getLivewire()->dispatch('$refresh');
            });
    }

    /**
     * Cria os serviços de apoio de um projeto Laravel (fila, agendador, pail)
     * reaproveitando o diretório do serviço atual, já na ordem de subida.
     */
    public static function companions(): Action
    {
        return Action::make('companions')
            ->label(__('services.actions.companions'))
            ->icon(Heroicon::OutlinedQueueList)
            ->color('info')
            ->visible(
                fn (Service $record): bool => app(DirectoryBrowser::class)->isLaravelProject($record->working_directory)
            )
            ->modalHeading(__('services.actions.companions'))
            ->modalDescription(__('services.companions.description'))
            ->modalSubmitActionLabel(__('services.companions.submit'))
            ->schema([
                CheckboxList::make('selected')
                    ->label(__('services.companions.field'))
                    ->options(
                        fn (): array => collect(DirectoryBrowser::laravelCompanions())
                            ->map(fn (array $companion): string => $companion['label'])
                            ->all()
                    )
                    ->descriptions(
                        fn (): array => collect(DirectoryBrowser::laravelCompanions())
                            ->map(fn (array $companion): string => $companion['command'])
                            ->all()
                    )
                    ->required(),

                Toggle::make('group_together')
                    ->label(__('services.companions.group_together'))
                    ->helperText(__('services.companions.group_together_hint'))
                    ->default(true),

                Toggle::make('start_now')
                    ->label(__('services.companions.start_now'))
                    ->default(false),
            ])
            ->action(function (Service $record, array $data, Action $action): void {
                $result = app(CompanionServiceFactory::class)->create(
                    $record,
                    (array) ($data['selected'] ?? []),
                    groupTogether: (bool) ($data['group_together'] ?? true),
                    startNow: (bool) ($data['start_now'] ?? false),
                );

                static::notify($result['created'] > 0, $result['message']);
                $action->getLivewire()->dispatch('$refresh');
            });
    }

    protected static function notify(bool $ok, string $message): void
    {
        $notification = Notification::make()->title($message);

        if ($ok) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }
}
