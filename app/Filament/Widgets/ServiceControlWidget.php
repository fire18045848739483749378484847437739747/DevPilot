<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceStatus;
use App\Filament\Resources\Services\Actions\ServiceActions;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Services\MetricsCollector;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Painel de controle rápido: lista todos os serviços com botões de
 * iniciar/parar/reiniciar diretamente no dashboard, sem precisar abrir
 * a tela de cada serviço.
 */
class ServiceControlWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.control_panel'))
            ->description(__('dashboard.control_panel_desc'))
            ->query(fn (): Builder => Service::query()->orderBy('name'))
            ->poll('5s')
            ->paginated(false)
            ->emptyStateHeading(__('dashboard.no_services'))
            ->emptyStateDescription(__('dashboard.no_services_desc'))
            ->emptyStateIcon(Heroicon::OutlinedServerStack)
            ->emptyStateActions([
                Action::make('create')
                    ->label(__('dashboard.create_service'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(ServiceResource::getUrl('create')),
            ])
            ->columns([
                TextColumn::make('status')
                    ->label(__('services.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ServiceStatus $state): string => $state->getLabel())
                    ->color(fn (ServiceStatus $state): string => $state->getColor())
                    ->icon(fn (ServiceStatus $state): string|BackedEnum => $state->getIcon()),

                TextColumn::make('name')
                    ->label(__('services.fields.name'))
                    ->weight('medium')
                    ->url(fn (Service $record): string => ServiceResource::getUrl('view', ['record' => $record]))
                    ->description(fn (Service $record): ?string => $record->accessUrl()),

                TextColumn::make('pid')
                    ->label(__('services.fields.pid'))
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('cpu_usage')
                    ->label(__('services.fields.cpu'))
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null ? '—' : number_format($state, 1) . '%'
                    )
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'danger',
                        $state >= 50 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),

                TextColumn::make('memory_kb')
                    ->label(__('services.fields.memory'))
                    ->formatStateUsing(
                        fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 1) . ' MB'
                    )
                    ->toggleable(),

                TextColumn::make('uptime_seconds')
                    ->label(__('services.fields.uptime'))
                    ->formatStateUsing(
                        fn (?int $state): string => $state === null ? '—' : gmdate('H:i:s', $state)
                    )
                    ->toggleable(),
            ])
            // Ações rápidas visíveis direto na linha (sem menu suspenso).
            ->recordActions([
                ServiceActions::start()->iconButton()->tooltip(__('services.actions.start')),
                ServiceActions::stop()->iconButton()->tooltip(__('services.actions.stop')),
                ServiceActions::restart()->iconButton()->tooltip(__('services.actions.restart')),
                ServiceActions::open()->iconButton()->tooltip(__('services.actions.open')),
                ActionGroup::make([
                    ServiceActions::kill(),
                    ServiceActions::logs(),
                    ServiceActions::clearLogs(),
                ]),
            ]);
    }

    /**
     * Mantém as métricas da tabela atualizadas mesmo sem o agente rodando.
     */
    protected function getTableQuery(): ?Builder
    {
        $services = Service::query()
            ->whereNotNull('pid')
            ->whereIn('status', [
                ServiceStatus::Running,
                ServiceStatus::Starting,
                ServiceStatus::Restarting,
            ])
            ->get();

        if ($services->isNotEmpty()) {
            app(MetricsCollector::class)->collect($services);
        }

        return parent::getTableQuery();
    }
}
