<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Navegação de diretórios do servidor, usada no formulário de cadastro para
 * escolher o diretório de execução sem precisar digitar o caminho.
 *
 * O navegador é restrito às raízes configuradas em `config/services_manager.php`
 * (`dev_roots`) — o navegador não pode listar o disco inteiro.
 */
class DirectoryBrowser
{
    /**
     * Raízes permitidas para navegação.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = collect(config('services_manager.dev_roots', []))
            ->map(fn (string $path): string => $this->normalize($path))
            ->filter(fn (string $path): bool => $path !== '' && File::isDirectory($path))
            ->unique()
            ->values()
            ->all();

        return $roots;
    }

    /**
     * Um caminho é permitido se estiver dentro de alguma das raízes.
     */
    public function isAllowed(string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === '') {
            return false;
        }

        foreach ($this->roots() as $root) {
            if ($path === $root || Str::startsWith($path . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subdiretórios imediatos de $path (ou as raízes, se $path for vazio).
     *
     * @return list<string>
     */
    public function children(?string $path = null): array
    {
        $path = $this->normalize((string) $path);

        if ($path === '') {
            return $this->roots();
        }

        if (! $this->isAllowed($path) || ! File::isDirectory($path)) {
            return [];
        }

        try {
            $directories = File::directories($path);
        } catch (\Throwable) {
            return [];
        }

        $directories = array_map(fn (string $dir): string => $this->normalize($dir), $directories);

        // Ignora diretórios ruidosos que nunca são o alvo de um serviço.
        $ignored = ['node_modules', 'vendor', '.git', '.idea', '.vscode'];

        $directories = array_values(array_filter(
            $directories,
            fn (string $dir): bool => ! in_array(basename($dir), $ignored, true)
        ));

        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        return $directories;
    }

    /**
     * Opções para o Select do formulário: as raízes, os filhos do caminho atual
     * e o diretório-pai (para poder subir um nível).
     *
     * @return array<string, string>
     */
    public function options(?string $current = null): array
    {
        $current = $this->normalize((string) $current);
        $options = [];

        if ($current !== '' && $this->isAllowed($current)) {
            $options[$current] = $current . '   ← ' . __('services.browser.selected');

            $parent = $this->normalize(dirname($current));

            if ($this->isAllowed($parent) && $parent !== $current) {
                $options[$parent] = $parent . '   ↑ ' . __('services.browser.parent');
            }

            foreach ($this->children($current) as $child) {
                $options[$child] = $child;
            }

            return $options;
        }

        foreach ($this->roots() as $root) {
            $options[$root] = $root;

            foreach ($this->children($root) as $child) {
                $options[$child] = $child;
            }
        }

        return $options;
    }

    /**
     * Busca por trecho do caminho, para o campo `searchable` do Select.
     *
     * @return array<string, string>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return $this->options();
        }

        // Se o usuário digitou/colou um caminho existente, navega para ele.
        $candidate = $this->normalize($term);

        if (File::isDirectory($candidate) && $this->isAllowed($candidate)) {
            return $this->options($candidate);
        }

        $matches = [];

        foreach ($this->roots() as $root) {
            foreach ($this->children($root) as $child) {
                if (Str::contains($child, $term, ignoreCase: true)) {
                    $matches[$child] = $child;
                }

                foreach ($this->children($child) as $grandChild) {
                    if (Str::contains($grandChild, $term, ignoreCase: true)) {
                        $matches[$grandChild] = $grandChild;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Indica se o diretório parece ser a raiz de um projeto Laravel.
     */
    public function isLaravelProject(?string $path): bool
    {
        $path = $this->normalize((string) $path);

        return $path !== '' && File::exists($path . DIRECTORY_SEPARATOR . 'artisan');
    }

    /**
     * Indica se o diretório tem um `package.json` com script `dev`.
     */
    public function isNodeProject(?string $path): bool
    {
        $path = $this->normalize((string) $path);

        if ($path === '') {
            return false;
        }

        $manifest = $path . DIRECTORY_SEPARATOR . 'package.json';

        if (! File::exists($manifest)) {
            return false;
        }

        $data = json_decode((string) File::get($manifest), true);

        return is_array($data) && isset($data['scripts']['dev']);
    }

    /**
     * Reconhece o tipo de projeto na pasta e devolve um comando e um nome
     * sugeridos, usados para pré-preencher o formulário de cadastro.
     *
     * @return array{type: string, name: string|null, command: string|null, needs_port: bool}
     */
    public function detect(?string $path, ?int $port = null, ?string $host = null): array
    {
        $path = $this->normalize((string) $path);

        if ($path === '') {
            return ['type' => 'unknown', 'name' => null, 'command' => null, 'needs_port' => false];
        }

        $name = basename($path);
        $host = $host ?: (string) config('services_manager.default_host', '127.0.0.1');

        if ($this->isLaravelProject($path)) {
            return [
                'type' => 'laravel',
                'name' => Str::headline($name),
                'command' => 'php artisan serve --host=' . $host . ($port ? ' --port=' . $port : ''),
                'needs_port' => true,
            ];
        }

        if ($this->isNodeProject($path)) {
            return [
                'type' => 'node',
                'name' => Str::headline($name),
                'command' => 'npm run dev',
                'needs_port' => false,
            ];
        }

        return [
            'type' => 'unknown',
            'name' => Str::headline($name),
            'command' => null,
            'needs_port' => false,
        ];
    }

    /**
     * Comandos de apoio de um projeto Laravel (fila, agendador, logs), usados
     * pelos atalhos que criam serviços companheiros.
     *
     * @return array<string, array{label: string, command: string, auto_restart: bool}>
     */
    public static function laravelCompanions(): array
    {
        return [
            'queue' => [
                'label' => __('services.companions.queue'),
                'command' => 'php artisan queue:work --tries=3',
                'auto_restart' => true,
            ],
            'schedule' => [
                'label' => __('services.companions.schedule'),
                'command' => 'php artisan schedule:work',
                'auto_restart' => true,
            ],
            'pail' => [
                'label' => __('services.companions.pail'),
                'command' => 'php artisan pail --timeout=0',
                'auto_restart' => false,
            ],
        ];
    }

    protected function normalize(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $real = realpath($path);

        return rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR);
    }
}
