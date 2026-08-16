<?php

namespace App\Filament\Pages;

use App\Models\Service;
use App\Services\DirectoryBrowser;
use App\Services\ProjectCommandCatalog;
use App\Services\ProjectCommandRunner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Comandos prontos executados na pasta do projeto.
 *
 * A lista vem de `ProjectCommandCatalog` (fechada, definida no config) e é
 * filtrada pelo que a pasta realmente suporta — não adianta oferecer
 * `filament:upgrade` em um projeto sem Filament.
 */
class ProjectCommandsPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.project-commands';

    public ?string $directory = null;

    public bool $showDangerous = false;

    public ?string $output = null;

    public ?string $lastCommand = null;

    public bool $lastOk = true;

    public ?float $lastDuration = null;

    public static function getNavigationLabel(): string
    {
        return __('commands.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('services.resource.navigation_group');
    }

    public function getTitle(): string
    {
        return __('commands.title');
    }

    public function mount(): void
    {
        $this->directory = $this->resolveInitialDirectory();
    }

    /**
     * Pasta inicial: a pedida na URL (link vindo do serviço) ou a do primeiro
     * serviço cadastrado que seja um projeto de verdade.
     */
    protected function resolveInitialDirectory(): ?string
    {
        $requested = request()->query('directory');

        if (is_string($requested) && app(DirectoryBrowser::class)->isAllowed($requested)) {
            return $requested;
        }

        $catalog = app(ProjectCommandCatalog::class);

        foreach ($this->directoryOptions() as $directory => $label) {
            if ($catalog->capabilities($directory) !== []) {
                return $directory;
            }
        }

        return null;
    }

    /**
     * Pastas conhecidas: as dos serviços cadastrados, sem repetição.
     *
     * @return array<string, string>
     */
    public function directoryOptions(): array
    {
        return Service::query()
            ->whereNotNull('working_directory')
            ->orderBy('name')
            ->pluck('working_directory')
            ->unique()
            ->filter(fn (?string $directory): bool => filled($directory))
            ->mapWithKeys(fn (string $directory): array => [$directory => $directory])
            ->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function getGroupedCommands(): array
    {
        return app(ProjectCommandCatalog::class)->forDirectory($this->directory, $this->showDangerous);
    }

    /**
     * @return list<string>
     */
    public function getCapabilities(): array
    {
        return app(ProjectCommandCatalog::class)->capabilities($this->directory);
    }

    public function run(string $key): void
    {
        $result = app(ProjectCommandRunner::class)->run($key, $this->directory);

        $this->output = $result['output'];
        $this->lastCommand = $result['command'];
        $this->lastOk = $result['ok'];
        $this->lastDuration = $result['duration'];

        Notification::make()
            ->title($result['ok']
                ? __('commands.messages.finished_ok', ['command' => $result['command']])
                : __('commands.messages.finished_error', ['command' => $result['command']]))
            ->status($result['ok'] ? 'success' : 'danger')
            ->send();
    }

    public function clearOutput(): void
    {
        $this->output = null;
        $this->lastCommand = null;
        $this->lastDuration = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chooseDirectory')
                ->label(__('commands.actions.choose_directory'))
                ->icon(Heroicon::OutlinedFolderOpen)
                ->schema([
                    Select::make('directory')
                        ->label(__('services.fields.working_directory'))
                        ->helperText(__('services.hints.working_directory'))
                        ->options(fn (): array => $this->directoryOptions()
                            + app(DirectoryBrowser::class)->options($this->directory))
                        ->getSearchResultsUsing(fn (string $search): array => app(DirectoryBrowser::class)->search($search))
                        ->getOptionLabelUsing(fn (?string $value): ?string => $value)
                        ->default(fn (): ?string => $this->directory)
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->directory = $data['directory'];
                    $this->clearOutput();
                }),

            Action::make('toggleDangerous')
                ->label(fn (): string => $this->showDangerous
                    ? __('commands.actions.hide_dangerous')
                    : __('commands.actions.show_dangerous'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($this->showDangerous ? 'danger' : 'gray')
                ->action(fn () => $this->showDangerous = ! $this->showDangerous),

            Action::make('clearOutput')
                ->label(__('commands.actions.clear_output'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->visible(fn (): bool => filled($this->output))
                ->action(fn () => $this->clearOutput()),
        ];
    }
}
