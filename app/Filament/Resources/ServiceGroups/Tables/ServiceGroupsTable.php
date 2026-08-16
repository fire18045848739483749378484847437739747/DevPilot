<?php

namespace App\Filament\Resources\ServiceGroups\Tables;

use App\Filament\Resources\ServiceGroups\Actions\ServiceGroupActions;
use App\Models\ServiceGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ServiceGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('name')
                    ->label(__('groups.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (ServiceGroup $record): string => $record->color ?: 'primary')
                    ->description(
                        fn (ServiceGroup $record): ?string => $record->description
                            ? Str::limit($record->description, 60)
                            : null
                    ),

                TextColumn::make('services_count')
                    ->label(__('groups.fields.services_count'))
                    ->counts('services')
                    ->alignCenter(),

                TextColumn::make('running')
                    ->label(__('groups.fields.running_count'))
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (ServiceGroup $record): int => $record->runningCount())
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('members')
                    ->label(__('groups.fields.members'))
                    ->listWithLineBreaks()
                    ->limitList(4)
                    ->expandableLimitedList()
                    // Ordem de subida do stack, na mesma sequência usada pela ação.
                    ->getStateUsing(
                        fn (ServiceGroup $record): array => $record->orderedServices()
                            ->get()
                            ->map(fn ($service): string => $service->boot_order . '. ' . $service->name
                                . ' — ' . $service->status->getLabel())
                            ->all()
                    ),

                TextColumn::make('start_delay_seconds')
                    ->label(__('groups.fields.start_delay_seconds'))
                    ->suffix(' s')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                ServiceGroupActions::start(),
                ServiceGroupActions::stop(),
                ActionGroup::make([
                    ServiceGroupActions::restart(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->emptyStateHeading(__('groups.empty.heading'))
            ->emptyStateDescription(__('groups.empty.description'));
    }
}
