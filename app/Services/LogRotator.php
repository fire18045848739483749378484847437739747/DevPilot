<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Rotação dos arquivos de log dos serviços.
 *
 * Cada start cria um par de arquivos novo em `storage/logs/services`, e nada
 * limpava os antigos. Aqui há duas frentes:
 *
 * 1. Arquivo do serviço em execução que passou do tamanho máximo é **truncado**
 *    (nunca apagado nem renomeado: o processo filho mantém o handle aberto, e
 *    remover o arquivo faria a saída dele sumir). A ingestão roda antes, então
 *    o conteúdo já está no banco.
 * 2. Arquivos antigos que não pertencem mais a nenhum serviço são apagados.
 */
class LogRotator
{
    public function __construct(protected LogIngestor $ingestor)
    {
    }

    protected function directory(): string
    {
        return storage_path('logs/services');
    }

    protected function markerPath(): string
    {
        return storage_path('app/services/logs.rotated');
    }

    /**
     * Roda a rotação apenas se já passou o intervalo configurado desde a última.
     *
     * @return array{rotated: int, deleted: int, freed_kb: int}|null
     */
    public function rotateIfDue(): ?array
    {
        $minutes = max(1, (int) config('services_manager.logs.rotate_interval_minutes', 10));
        $marker = $this->markerPath();

        if (File::exists($marker)) {
            $last = Carbon::createFromTimestamp(File::lastModified($marker));

            if ($last->diffInMinutes(now(), absolute: true) < $minutes) {
                return null;
            }
        }

        return $this->rotate();
    }

    /**
     * @return array{rotated: int, deleted: int, freed_kb: int}
     */
    public function rotate(): array
    {
        $rotated = 0;
        $deleted = 0;
        $freed = 0;

        $maxBytes = max(1, (int) config('services_manager.logs.rotate_size_mb', 10)) * 1024 * 1024;
        $active = [];

        foreach (Service::query()->get() as $service) {
            $truncated = false;

            foreach ([$service->log_path_out, $service->log_path_err] as $path) {
                if (! $path) {
                    continue;
                }

                $active[$this->key($path)] = true;

                if (! is_file($path)) {
                    continue;
                }

                clearstatcache(true, $path);
                $size = (int) @filesize($path);

                if ($size < $maxBytes) {
                    continue;
                }

                // Grava no banco o que ainda não foi capturado antes de zerar.
                $this->ingestor->ingest($service);

                if (@file_put_contents($path, '') === false) {
                    continue;
                }

                $freed += (int) ($size / 1024);
                $rotated++;
                $truncated = true;
            }

            if ($truncated) {
                $service->forceFill(['log_offset_out' => 0, 'log_offset_err' => 0])->saveQuietly();
            }
        }

        $deleted += $this->purgeOrphans($active, $freed);

        File::ensureDirectoryExists(dirname($this->markerPath()));
        File::put($this->markerPath(), now()->toIso8601String());

        return ['rotated' => $rotated, 'deleted' => $deleted, 'freed_kb' => $freed];
    }

    /**
     * Apaga arquivos antigos que não são mais o log corrente de nenhum serviço.
     *
     * @param  array<string, true>  $active
     */
    protected function purgeOrphans(array $active, int &$freed): int
    {
        $dir = $this->directory();

        if (! File::isDirectory($dir)) {
            return 0;
        }

        $days = max(0, (int) config('services_manager.logs.retention_days', 7));
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (File::files($dir) as $file) {
            $path = $file->getPathname();

            if (isset($active[$this->key($path)])) {
                continue;
            }

            if ($file->getMTime() > $cutoff) {
                continue;
            }

            $size = (int) $file->getSize();

            if (@unlink($path)) {
                $freed += (int) ($size / 1024);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Chave de comparação insensível a maiúsculas/minúsculas e a separadores —
     * o caminho gravado no banco e o devolvido pelo `File::files()` podem
     * diferir na forma, mesmo apontando para o mesmo arquivo no Windows.
     */
    protected function key(string $path): string
    {
        return strtolower(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
