<?php

namespace App\Filament\Widgets;

use App\Enums\LogLevel;
use App\Models\Service;
use App\Models\ServiceLog;
use App\Services\LogIngestor;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico de logs do serviço vindo do banco — o que permite buscar por texto
 * e filtrar por nível, coisa que o `tail` do arquivo (ServiceLogsWidget) não
 * faz. A captura roda aqui mesmo, então a tabela também funciona sem o agente.
 */
class ServiceLogTableWidget extends TableWidget
{
    public Service $record;

    protected int|string|array $columnSpan = 'full';

    /**
     * O `TableWidget::makeTable()` sobrescreve o `heading()` definido em
     * `table()` com um título derivado do nome da classe — o título precisa
     * vir daqui para valer.
     */
    protected function getTableHeading(): string|Htmlable|null
    {
        return __('widgets.log_history');
    }

    public function table(Table $table): Table
    {
        // Leitura incremental do arquivo: só I/O, sem PowerShell.
        app(LogIngestor::class)->ingest($this->record);

        return $table
            ->description(__('widgets.log_history_desc'))
            ->poll('10s')
            ->query(
                fn (): Builder => ServiceLog::query()->where('service_id', $this->record->getKey())
            )
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('services.fields.logged_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->extraAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('level')
                    ->label(__('services.fields.log_level'))
                    ->badge()
                    ->formatStateUsing(fn (LogLevel $state): string => $state->getLabel())
                    ->color(fn (LogLevel $state): string => $state->getColor())
                    ->icon(fn (LogLevel $state): string|BackedEnum => $state->getIcon()),

                TextColumn::make('type')
                    ->label(__('services.fields.log_type'))
                    ->formatStateUsing(fn (string $state): string => ServiceLog::typeOptions()[$state] ?? $state)
                    ->toggleable(),

                TextColumn::make('message')
                    ->label(__('services.fields.log_message'))
                    ->searchable()
                    ->wrap()
                    ->fontFamily('mono')
                    ->size('xs')
                    ->limit(300),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label(__('services.fields.log_level'))
                    ->multiple()
                    ->options(LogLevel::class),

                SelectFilter::make('type')
                    ->label(__('services.fields.log_type'))
                    ->multiple()
                    ->options(fn (): array => ServiceLog::typeOptions()),
            ])
            ->emptyStateHeading(__('widgets.log_history_empty'))
            ->emptyStateDescription(__('widgets.log_history_empty_desc'));
    }
}
