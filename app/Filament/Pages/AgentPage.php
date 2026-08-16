<?php

namespace App\Filament\Pages;

use App\Services\AgentInstaller;
use App\Services\AgentState;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Registro do agente como serviço do Windows.
 *
 * Enquanto o agente depender de um terminal aberto, fechar o terminal desliga
 * o reinício automático e o `auto_start_on_boot` — e as métricas do painel
 * param de ser atualizadas em segundo plano.
 */
class AgentPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.agent';

    /**
     * @var array{installed: bool, method: string|null, state: string|null, detail: string|null,
     *            heartbeat: bool, elevated: bool}|null
     */
    public ?array $agentStatus = null;

    public static function getNavigationLabel(): string
    {
        return __('agent.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('services.resource.navigation_group');
    }

    public function getTitle(): string
    {
        return __('agent.title');
    }

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $this->agentStatus = app(AgentInstaller::class)->status();
    }

    public function getLastHeartbeat(): ?string
    {
        return AgentState::lastHeartbeat()?->format('d/m/Y H:i:s');
    }

    /**
     * @return array<string, string>
     */
    public function getInstallerDetails(): array
    {
        $installer = app(AgentInstaller::class);

        return [
            __('agent.details.php') => $installer->phpBinary(),
            __('agent.details.project') => $installer->projectPath(),
            __('agent.details.command') => 'php ' . $installer->agentArguments(),
            __('agent.details.task_name') => $installer->taskName(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label(__('agent.actions.install'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('success')
                ->visible(fn (): bool => ! ($this->agentStatus['installed'] ?? false))
                ->schema([
                    Select::make('method')
                        ->label(__('agent.fields.method'))
                        ->options([
                            AgentInstaller::METHOD_TASK => __('agent.methods.task'),
                            AgentInstaller::METHOD_NSSM => __('agent.methods.nssm'),
                        ])
                        ->default(AgentInstaller::METHOD_TASK)
                        ->native(false)
                        ->required(),

                    Select::make('trigger')
                        ->label(__('agent.fields.trigger'))
                        ->helperText(__('agent.hints.trigger'))
                        ->options([
                            AgentInstaller::TRIGGER_LOGON => __('agent.triggers.logon'),
                            AgentInstaller::TRIGGER_BOOT => __('agent.triggers.boot'),
                        ])
                        ->default(AgentInstaller::TRIGGER_LOGON)
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->handle(app(AgentInstaller::class)->install(
                        (string) $data['method'],
                        (string) $data['trigger'],
                    ));
                }),

            Action::make('start')
                ->label(__('agent.actions.start'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => ($this->agentStatus['installed'] ?? false))
                ->action(fn () => $this->handle(app(AgentInstaller::class)->start())),

            Action::make('stop')
                ->label(__('agent.actions.stop'))
                ->icon(Heroicon::OutlinedStop)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('agent.confirmations.stop'))
                ->visible(fn (): bool => ($this->agentStatus['installed'] ?? false))
                ->action(fn () => $this->handle(app(AgentInstaller::class)->stop())),

            Action::make('uninstall')
                ->label(__('agent.actions.uninstall'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('agent.confirmations.uninstall'))
                ->visible(fn (): bool => ($this->agentStatus['installed'] ?? false))
                ->action(fn () => $this->handle(app(AgentInstaller::class)->uninstall())),

            Action::make('refresh')
                ->label(__('agent.actions.refresh'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->refreshStatus()),
        ];
    }

    /**
     * @param  array{ok: bool, message: string, command?: string}  $result
     */
    protected function handle(array $result): void
    {
        $notification = Notification::make()->title($result['message']);

        if ($result['ok']) {
            $notification->success();
        } else {
            $notification->danger()->persistent();

            // Sem elevação não dá para registrar tarefa/serviço: entregamos o
            // comando pronto para colar em um terminal de administrador.
            if (isset($result['command'])) {
                $notification->body($result['command']);
            }
        }

        $notification->send();

        $this->refreshStatus();
    }
}
