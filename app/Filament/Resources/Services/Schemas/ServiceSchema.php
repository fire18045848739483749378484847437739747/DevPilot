<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\RestartPolicy;
use App\Enums\ServiceEnvironment;
use App\Filament\Resources\ServiceGroups\Schemas\ServiceGroupSchema;
use App\Models\ServiceGroup;
use App\Services\CommandRewriter;
use App\Services\DirectoryBrowser;
use App\Services\NetworkAddresses;
use App\Services\PortAllocator;
use Filament\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ServiceSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('services.section_identification'))
                ->description(__('services.section_identification_desc'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('services.fields.name'))
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('slug')
                        ->label(__('services.fields.slug'))
                        ->required()
                        ->maxLength(120)
                        ->unique(table: 'services', ignoreRecord: true)
                        ->disabled()
                        ->dehydrated()
                        ->visibleOn(Operation::Create),

                    Select::make('service_group_id')
                        ->label(__('services.fields.group'))
                        ->helperText(__('services.hints.group'))
                        ->relationship('group', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->createOptionForm(ServiceGroupSchema::components())
                        ->createOptionUsing(fn (array $data): int => ServiceGroup::create($data)->getKey()),

                    TextInput::make('boot_order')
                        ->label(__('services.fields.boot_order'))
                        ->helperText(__('services.hints.boot_order'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),

                    Textarea::make('description')
                        ->label(__('services.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make(__('services.section_execution'))
                ->description(__('services.section_execution_desc'))
                ->columns(2)
                ->schema([
                    Textarea::make('command')
                        ->label(__('services.fields.command'))
                        ->required()
                        ->rows(2)
                        ->placeholder('php artisan serve --host=192.168.0.11 --port=8002')
                        ->helperText(__('services.hints.command'))
                        // Live para o seletor de host saber se este comando
                        // aceita host (um `queue:work` não aceita).
                        ->live(onBlur: true)
                        ->columnSpanFull(),

                    Select::make('working_directory')
                        ->label(__('services.fields.working_directory'))
                        ->placeholder(__('services.browser.placeholder'))
                        ->helperText(__('services.hints.working_directory'))
                        ->searchable()
                        ->allowHtml(false)
                        ->native(false)
                        // Navegação de pastas do servidor: cada seleção recarrega
                        // as opções com os subdiretórios do caminho escolhido.
                        ->options(fn (?string $state): array => app(DirectoryBrowser::class)->options($state))
                        ->getSearchResultsUsing(fn (string $search): array => app(DirectoryBrowser::class)->search($search))
                        ->getOptionLabelUsing(fn (?string $value): ?string => $value)
                        ->live()
                        // Detecta o tipo de projeto na pasta escolhida e
                        // pré-preenche o que ainda estiver vazio. Nunca
                        // sobrescreve o que o usuário já digitou.
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (! $state) {
                                return;
                            }

                            $browser = app(DirectoryBrowser::class);
                            $detected = $browser->detect($state);

                            if ($detected['command'] === null) {
                                return;
                            }

                            $port = $get('port');

                            if ($detected['needs_port'] && blank($port)) {
                                $port = app(PortAllocator::class)->nextFree();

                                if ($port) {
                                    $set('port', $port);
                                }
                            }

                            if (blank($get('command'))) {
                                $host = $get('host') ?: config('services_manager.default_host');

                                $set('command', $browser->detect($state, $port ? (int) $port : null, $host)['command']);
                            }

                            if (blank($get('name')) && $detected['name']) {
                                $set('name', $detected['name']);
                                $set('slug', Str::slug($detected['name']));
                            }
                        })
                        ->suffixAction(
                            FormAction::make('browseUp')
                                ->label(__('services.browser.parent'))
                                ->icon(Heroicon::OutlinedArrowUp)
                                ->action(function (Set $set, ?string $state): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $parent = dirname($state);

                                    if (app(DirectoryBrowser::class)->isAllowed($parent)) {
                                        $set('working_directory', $parent);
                                    }
                                })
                        )
                        ->columnSpanFull(),

                    // O host não é coluna: ele vive dentro do `command`. Este
                    // campo apenas lê e reescreve essa parte da linha.
                    Select::make('host')
                        ->label(__('services.fields.host'))
                        ->helperText(__('services.hints.host'))
                        ->native(false)
                        ->dehydrated(false)
                        ->live()
                        // Só aparece para comandos que realmente têm host:
                        // oferecer a escolha em um `queue:work` seria mentira,
                        // porque não há o que reescrever na linha.
                        ->visible(fn (Get $get): bool => blank($get('command'))
                            || CommandRewriter::supportsHost($get('command')))
                        ->options(function (?string $state): array {
                            $options = app(NetworkAddresses::class)->options();

                            // O comando pode apontar para um IP que não existe
                            // mais na máquina; sem isto o Select mostraria vazio.
                            if ($state && ! array_key_exists($state, $options)) {
                                $options[$state] = $state . ' — ' . __('services.hosts.unknown_interface');
                            }

                            return $options;
                        })
                        ->afterStateHydrated(function (Set $set, Get $get): void {
                            $set('host', CommandRewriter::extractHost($get('command'))
                                ?? config('services_manager.default_host', NetworkAddresses::LOCALHOST));
                        })
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            $set('command', CommandRewriter::withHost($get('command'), $state));
                        })
                        ->suffixAction(
                            FormAction::make('detectIp')
                                ->label(__('services.actions.detect_ip'))
                                ->icon(Heroicon::OutlinedGlobeAlt)
                                ->action(function (Set $set, Get $get): void {
                                    $network = app(NetworkAddresses::class);
                                    $network->forget();

                                    $ip = $network->primary();

                                    if (! $ip) {
                                        Notification::make()
                                            ->title(__('services.messages.no_network_ip'))
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $set('host', $ip);
                                    $set('command', CommandRewriter::withHost($get('command'), $ip));
                                })
                        ),

                    Select::make('environment')
                        ->label(__('services.fields.environment'))
                        ->options(ServiceEnvironment::class)
                        ->default(ServiceEnvironment::Development)
                        ->native(false),

                    TextInput::make('user')
                        ->label(__('services.fields.user'))
                        ->placeholder('www-data'),

                    TextInput::make('port')
                        ->label(__('services.fields.port'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->helperText(__('services.hints.port'))
                        ->suffixAction(
                            FormAction::make('suggestPort')
                                ->label(__('services.actions.suggest_port'))
                                ->icon(Heroicon::OutlinedSparkles)
                                ->action(fn (Set $set) => $set('port', app(PortAllocator::class)->nextFree()))
                        ),

                    Select::make('url_scheme')
                        ->label(__('services.fields.url_scheme'))
                        ->options(['http' => 'http', 'https' => 'https'])
                        ->default('http')
                        ->native(false),

                    TextInput::make('url_path')
                        ->label(__('services.fields.url_path'))
                        ->placeholder('admin')
                        ->helperText(__('services.hints.url_path')),

                    KeyValue::make('env_vars')
                        ->label(__('services.fields.env_vars'))
                        ->keyLabel(__('services.fields.env_var_name'))
                        ->valueLabel(__('services.fields.env_var_value'))
                        ->addActionLabel(__('services.actions.add_env_var'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('services.section_behavior'))
                ->description(__('services.section_behavior_desc'))
                ->columns(3)
                ->schema([
                    Toggle::make('auto_start_on_boot')
                        ->label(__('services.fields.auto_start_on_boot'))
                        ->helperText(__('services.hints.auto_start_on_boot'))
                        ->default(false),

                    Toggle::make('auto_restart')
                        ->label(__('services.fields.auto_restart'))
                        ->helperText(__('services.hints.auto_restart'))
                        ->default(false),

                    Select::make('restart_policy')
                        ->label(__('services.fields.restart_policy'))
                        ->options(RestartPolicy::class)
                        ->default(RestartPolicy::OnFailure)
                        ->native(false),

                    TextInput::make('max_restarts')
                        ->label(__('services.fields.max_restarts'))
                        ->numeric()
                        ->minValue(0)
                        ->default(5),

                    TextInput::make('stop_timeout')
                        ->label(__('services.fields.stop_timeout'))
                        ->numeric()
                        ->minValue(1)
                        ->default(10)
                        ->suffix(__('services.units.seconds')),

                    Toggle::make('start_on_create')
                        ->label(__('services.fields.start_on_create'))
                        ->helperText(__('services.hints.start_on_create'))
                        ->default(true)
                        ->dehydrated(false)
                        ->visibleOn(Operation::Create),
                ]),

            Section::make(__('services.section_health'))
                ->description(__('services.section_health_desc'))
                ->columns(4)
                ->schema([
                    Toggle::make('health_check_enabled')
                        ->label(__('services.fields.health_check_enabled'))
                        ->helperText(__('services.hints.health_check_enabled'))
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('health_check_path')
                        ->label(__('services.fields.health_check_path'))
                        ->placeholder('/up')
                        ->helperText(__('services.hints.health_check_path'))
                        ->visible(fn (Get $get): bool => (bool) $get('health_check_enabled'))
                        ->columnSpan(2),

                    TextInput::make('health_check_status')
                        ->label(__('services.fields.health_check_status'))
                        ->numeric()
                        ->minValue(100)
                        ->maxValue(599)
                        ->default(200)
                        ->visible(fn (Get $get): bool => (bool) $get('health_check_enabled')),

                    TextInput::make('health_check_timeout')
                        ->label(__('services.fields.health_check_timeout'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60)
                        ->default(5)
                        ->suffix(__('services.units.seconds'))
                        ->visible(fn (Get $get): bool => (bool) $get('health_check_enabled')),
                ]),

            Section::make(__('services.section_alerts'))
                ->description(__('services.section_alerts_desc'))
                ->columns(3)
                ->schema([
                    Toggle::make('alerts_enabled')
                        ->label(__('services.fields.alerts_enabled'))
                        ->helperText(__('services.hints.alerts_enabled'))
                        ->default(true)
                        ->live(),

                    TextInput::make('alert_cpu_threshold')
                        ->label(__('services.fields.alert_cpu_threshold'))
                        ->helperText(__('services.hints.alert_threshold'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Get $get): bool => (bool) $get('alerts_enabled')),

                    TextInput::make('alert_memory_threshold_mb')
                        ->label(__('services.fields.alert_memory_threshold_mb'))
                        ->helperText(__('services.hints.alert_threshold'))
                        ->numeric()
                        ->minValue(1)
                        ->suffix('MB')
                        ->visible(fn (Get $get): bool => (bool) $get('alerts_enabled')),
                ]),
        ]);
    }
}
