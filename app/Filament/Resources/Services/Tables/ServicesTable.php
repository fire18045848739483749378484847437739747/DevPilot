<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\HealthStatus;
use App\Enums\ServiceEnvironment;
use App\Enums\ServiceStatus;
use App\Filament\Resources\Services\Actions\ServiceActions;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                BadgeColumn::make('status')
                    ->label(__('services.fields.status'))
                    ->formatStateUsing(fn (ServiceStatus $state): string => $state->getLabel())
                    ->color(fn (ServiceStatus $state): string => $state->getColor())
                    ->icon(fn (ServiceStatus $state): string|BackedEnum => $state->getIcon())
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('services.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->description(
                        fn (Service $record): ?string => $record->description
                            ? Str::limit($record->description, 60)
                            : null
                    ),

                TextColumn::make('group.name')
                    ->label(__('services.fields.group'))
                    ->badge()
                    ->color(fn (Service $record): string => $record->group?->color ?: 'gray')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                BadgeColumn::make('health_status')
                    ->label(__('services.fields.health_status'))
                    ->formatStateUsing(fn (?HealthStatus $state): string => $state?->getLabel() ?? '—')
                    ->color(fn (?HealthStatus $state): string => $state?->getColor() ?? 'gray')
                    ->icon(fn (?HealthStatus $state): string|BackedEnum|null => $state?->getIcon())
                    ->placeholder('—')
                    ->toggleable(),

                BadgeColumn::make('environment')
                    ->label(__('services.fields.environment'))
                    ->formatStateUsing(fn (ServiceEnvironment $state): string => $state->getLabel())
                    ->color(fn (ServiceEnvironment $state): string => $state->getColor())
                    ->sortable(),

                TextColumn::make('port')
                    ->label(__('services.fields.port'))
                    ->sortable()
                    ->placeholder('—'),

                IconColumn::make('port_open')
                    ->label(__('services.fields.port_open'))
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('access_url')
                    ->label(__('services.fields.access_url'))
                    ->getStateUsing(fn (Service $record): ?string => $record->accessUrl())
                    ->url(fn (Service $record): ?string => $record->accessUrl(), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('info')
                    ->copyable()
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('pid')
                    ->label(__('services.fields.pid'))
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('cpu_usage')
                    ->label(__('services.fields.cpu'))
                    ->toggleable()
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null
                            ? '—'
                            : number_format($state, 1) . '%'
                    )
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'danger',
                        $state >= 50 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('memory_kb')
                    ->label(__('services.fields.memory'))
                    ->toggleable()
                    ->formatStateUsing(
                        fn (?int $state): string => $state === null
                            ? '—'
                            : number_format($state / 1024, 1) . ' MB'
                    ),

                TextColumn::make('uptime_seconds')
                    ->label(__('services.fields.uptime'))
                    ->toggleable()
                    ->formatStateUsing(
                        fn (?int $state): string => $state === null
                            ? '—'
                            : gmdate('H:i:s', $state)
                    ),

                TextColumn::make('restart_count')
                    ->label(__('services.fields.restarts'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('services.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('services.fields.status'))
                    ->options(ServiceStatus::class)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('environment')
                    ->label(__('services.fields.environment'))
                    ->options(ServiceEnvironment::class)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('service_group_id')
                    ->label(__('services.fields.group'))
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('health_status')
                    ->label(__('services.fields.health_status'))
                    ->options(HealthStatus::class),
            ])
            ->recordActions([
                ServiceActions::open(),
                ActionGroup::make([
                    ServiceActions::start(),
                    ServiceActions::stop(),
                    ServiceActions::restart(),
                    ServiceActions::kill(),
                    ServiceActions::checkHealth(),
                    ServiceActions::companions(),
                    ServiceActions::projectCommands(),
                    ServiceActions::logs(),
                    ServiceActions::clearLogs(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ]);
    }
}
