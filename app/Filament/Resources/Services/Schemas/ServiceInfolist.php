<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\HealthStatus;
use App\Enums\RestartPolicy;
use App\Enums\ServiceEnvironment;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('services.section_identification'))
                ->columns(2)
                ->schema([
                    TextEntry::make('status')
                        ->label(__('services.fields.status'))
                        ->badge()
                        ->color(fn (ServiceStatus $state): string => $state->getColor()),
                    TextEntry::make('name')
                        ->label(__('services.fields.name')),
                    TextEntry::make('slug')
                        ->label(__('services.fields.slug')),
                    TextEntry::make('group.name')
                        ->label(__('services.fields.group'))
                        ->badge()
                        ->color(fn (Service $record): string => $record->group?->color ?: 'gray')
                        ->placeholder('—'),
                    TextEntry::make('boot_order')
                        ->label(__('services.fields.boot_order'))
                        ->placeholder('—'),
                    TextEntry::make('description')
                        ->label(__('services.fields.description'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('services.section_execution'))
                ->columns(2)
                ->schema([
                    TextEntry::make('command')
                        ->label(__('services.fields.command'))
                        ->copyable()
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                    TextEntry::make('working_directory')
                        ->label(__('services.fields.working_directory'))
                        ->copyable()
                        ->fontFamily('mono'),
                    TextEntry::make('environment')
                        ->label(__('services.fields.environment'))
                        ->badge()
                        ->color(fn (ServiceEnvironment $state): string => $state->getColor()),
                    TextEntry::make('user')
                        ->label(__('services.fields.user'))
                        ->placeholder('—'),
                    TextEntry::make('port')
                        ->label(__('services.fields.port'))
                        ->placeholder('—'),
                    IconEntry::make('port_open')
                        ->label(__('services.fields.port_open'))
                        ->boolean(),
                    TextEntry::make('access_url')
                        ->label(__('services.fields.access_url'))
                        ->getStateUsing(fn (Service $record): ?string => $record->accessUrl())
                        ->url(fn (Service $record): ?string => $record->accessUrl(), shouldOpenInNewTab: true)
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('primary')
                        ->copyable()
                        ->fontFamily('mono')
                        ->placeholder('—')
                        ->helperText(__('services.hints.access_url'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('services.section_monitoring'))
                ->columns(3)
                ->schema([
                    TextEntry::make('pid')
                        ->label(__('services.fields.pid'))
                        ->placeholder('—')
                        ->fontFamily('mono'),
                    TextEntry::make('cpu_usage')
                        ->label(__('services.fields.cpu'))
                        ->formatStateUsing(
                            fn (?float $state): string => $state === null
                                ? '—'
                                : number_format($state, 1) . '%'
                        ),
                    TextEntry::make('memory_kb')
                        ->label(__('services.fields.memory'))
                        ->formatStateUsing(
                            fn (?int $state): string => $state === null
                                ? '—'
                                : number_format($state / 1024, 1) . ' MB'
                        ),
                    TextEntry::make('started_at')
                        ->label(__('services.fields.started_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('uptime_seconds')
                        ->label(__('services.fields.uptime'))
                        ->formatStateUsing(
                            fn (?int $state): string => $state === null
                                ? '—'
                                : gmdate('H:i:s', $state)
                        ),
                    TextEntry::make('exit_code')
                        ->label(__('services.fields.exit_code'))
                        ->placeholder('—'),
                ]),

            Section::make(__('services.section_health'))
                ->columns(4)
                ->visible(fn (Service $record): bool => $record->health_check_enabled)
                ->schema([
                    TextEntry::make('health_status')
                        ->label(__('services.fields.health_status'))
                        ->badge()
                        ->formatStateUsing(fn (?HealthStatus $state): string => $state?->getLabel() ?? '—')
                        ->color(fn (?HealthStatus $state): string => $state?->getColor() ?? 'gray')
                        ->placeholder('—'),
                    TextEntry::make('health_last_code')
                        ->label(__('services.fields.health_last_code'))
                        ->placeholder('—'),
                    TextEntry::make('health_failures')
                        ->label(__('services.fields.health_failures')),
                    TextEntry::make('health_checked_at')
                        ->label(__('services.fields.health_checked_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('health_url')
                        ->label(__('services.fields.health_url'))
                        ->getStateUsing(fn (Service $record): ?string => $record->healthUrl())
                        ->fontFamily('mono')
                        ->copyable()
                        ->placeholder('—')
                        ->columnSpan(2),
                    TextEntry::make('health_error')
                        ->label(__('services.fields.health_error'))
                        ->color('danger')
                        ->placeholder('—')
                        ->columnSpan(2),
                ]),

            Section::make(__('services.section_alerts'))
                ->columns(3)
                ->schema([
                    IconEntry::make('alerts_enabled')
                        ->label(__('services.fields.alerts_enabled'))
                        ->boolean(),
                    TextEntry::make('alert_cpu_threshold')
                        ->label(__('services.fields.alert_cpu_threshold'))
                        ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : $state . '%')
                        ->placeholder('—'),
                    TextEntry::make('alert_memory_threshold_mb')
                        ->label(__('services.fields.alert_memory_threshold_mb'))
                        ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state . ' MB')
                        ->placeholder('—'),
                ]),

            Section::make(__('services.section_behavior'))
                ->columns(3)
                ->schema([
                    IconEntry::make('auto_start_on_boot')
                        ->label(__('services.fields.auto_start_on_boot'))
                        ->boolean(),
                    IconEntry::make('auto_restart')
                        ->label(__('services.fields.auto_restart'))
                        ->boolean(),
                    TextEntry::make('restart_policy')
                        ->label(__('services.fields.restart_policy'))
                        ->badge()
                        ->color(fn (RestartPolicy $state): string => $state->getColor()),
                    TextEntry::make('max_restarts')
                        ->label(__('services.fields.max_restarts')),
                    TextEntry::make('restart_count')
                        ->label(__('services.fields.restarts')),
                    TextEntry::make('stop_timeout')
                        ->label(__('services.fields.stop_timeout'))
                        ->formatStateUsing(fn (int $state): string => $state . ' ' . __('services.units.seconds')),
                    TextEntry::make('last_error')
                        ->label(__('services.fields.last_error'))
                        ->color('danger')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(__('services.section_environment'))
                ->columns(2)
                ->schema([
                    TextEntry::make('env_vars')
                        ->label(__('services.fields.env_vars'))
                        ->listWithLineBreaks()
                        ->getStateUsing(
                            fn (Service $record): array => collect($record->env_vars ?? [])
                                ->map(fn (string $value, string $key): string => $key . '=' . Str::limit($value, 40))
                                ->values()
                                ->all()
                        )
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
