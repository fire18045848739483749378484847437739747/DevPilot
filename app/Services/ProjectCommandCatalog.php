<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Catálogo de comandos prontos para rodar na pasta de um projeto.
 *
 * A lista é fechada de propósito: o painel executa apenas o que está em
 * `config/services_manager.php`, e não uma linha de comando digitada na hora.
 * Cada item declara de que tipo de projeto ele depende, e a filtragem por
 * pasta esconde o que não faz sentido ali (nada de `filament:upgrade` numa
 * pasta sem Filament).
 */
class ProjectCommandCatalog
{
    /** Ordem de exibição dos grupos na página. */
    public const GROUPS = ['info', 'cache', 'database', 'queue', 'filament', 'build'];

    public function __construct(protected DirectoryBrowser $browser)
    {
    }

    /**
     * Todos os itens do catálogo, normalizados.
     *
     * @return array<string, array{key: string, group: string, requires: string, command: string,
     *                             danger: bool, timeout: int, label: string, description: string}>
     */
    public function all(): array
    {
        $items = [];

        foreach ((array) config('services_manager.commands.catalog', []) as $key => $item) {
            if (! is_array($item) || blank($item['command'] ?? null)) {
                continue;
            }

            $items[$key] = [
                'key' => $key,
                'group' => $item['group'] ?? 'info',
                'requires' => $item['requires'] ?? 'laravel',
                'command' => $item['command'],
                'danger' => (bool) ($item['danger'] ?? false),
                'timeout' => (int) ($item['timeout'] ?? config('services_manager.commands.timeout', 300)),
                'label' => $item['label'] ?? __('commands.items.' . $key . '.label'),
                'description' => $item['description'] ?? __('commands.items.' . $key . '.description'),
            ];
        }

        return $items;
    }

    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Itens aplicáveis à pasta, agrupados e na ordem de `GROUPS`.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function forDirectory(?string $directory, bool $includeDangerous = false): array
    {
        $capabilities = $this->capabilities($directory);
        $grouped = [];

        foreach ($this->all() as $item) {
            if (! in_array($item['requires'], $capabilities, true)) {
                continue;
            }

            if ($item['danger'] && ! $includeDangerous) {
                continue;
            }

            $grouped[$item['group']][] = $item;
        }

        return $this->sortGroups($grouped);
    }

    /**
     * Um comando só pode rodar se a pasta suportar o que ele exige.
     */
    public function isAvailable(array $item, ?string $directory): bool
    {
        return in_array($item['requires'], $this->capabilities($directory), true);
    }

    /**
     * O que a pasta oferece: laravel (tem `artisan`), filament (tem o pacote
     * instalado), composer (`composer.json`), node (`package.json`).
     *
     * @return list<string>
     */
    public function capabilities(?string $directory): array
    {
        $directory = trim((string) $directory);

        if ($directory === '' || ! File::isDirectory($directory)) {
            return [];
        }

        $capabilities = [];

        if ($this->browser->isLaravelProject($directory)) {
            $capabilities[] = 'laravel';
        }

        if (File::exists($directory . DIRECTORY_SEPARATOR . 'composer.json')) {
            $capabilities[] = 'composer';
        }

        if (File::exists($directory . DIRECTORY_SEPARATOR . 'package.json')) {
            $capabilities[] = 'node';
        }

        if ($this->hasFilament($directory)) {
            $capabilities[] = 'filament';
        }

        return $capabilities;
    }

    /**
     * O `vendor/filament` é a prova de que o pacote está instalado; o
     * `composer.json` cobre o caso de o vendor ainda não ter sido baixado.
     */
    protected function hasFilament(string $directory): bool
    {
        if (File::isDirectory($directory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'filament')) {
            return true;
        }

        $manifest = $directory . DIRECTORY_SEPARATOR . 'composer.json';

        if (! File::exists($manifest)) {
            return false;
        }

        $data = json_decode((string) File::get($manifest), true);

        return is_array($data) && isset($data['require']['filament/filament']);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    protected function sortGroups(array $grouped): array
    {
        $sorted = [];

        foreach (self::GROUPS as $group) {
            if (isset($grouped[$group])) {
                $sorted[$group] = $grouped[$group];
            }
        }

        // Grupos criados pelo usuário no config entram depois dos conhecidos.
        foreach ($grouped as $group => $items) {
            if (! isset($sorted[$group])) {
                $sorted[$group] = $items;
            }
        }

        return $sorted;
    }
}
