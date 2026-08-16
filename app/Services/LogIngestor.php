<?php

namespace App\Services;

use App\Enums\LogLevel;
use App\Models\Service;
use App\Models\ServiceLog;
use Illuminate\Support\Str;

/**
 * Captura o stdout/stderr dos serviços para a tabela `service_logs`.
 *
 * O processo filho escreve em arquivo (redirecionamento do `Start-Process`), e
 * não há como fazer um pipe com o processo já desacoplado do terminal. Então a
 * captura é incremental: cada serviço guarda o offset em bytes já lido de cada
 * arquivo e, a cada passagem, apenas o trecho novo vira linhas no banco.
 *
 * A leitura é barata (só I/O de arquivo, sem PowerShell), então roda tanto no
 * agente quanto nas telas do painel.
 */
class LogIngestor
{
    /**
     * Ingere os logs de vários serviços.
     *
     * @param  iterable<Service>  $services
     * @return int  Total de linhas gravadas.
     */
    public function ingestMany(iterable $services): int
    {
        $total = 0;

        foreach ($services as $service) {
            $total += $this->ingest($service);
        }

        return $total;
    }

    /**
     * @return int  Linhas gravadas para este serviço.
     */
    public function ingest(Service $service): int
    {
        if (! config('services_manager.logs.ingest', true)) {
            return 0;
        }

        $written = 0;
        $updates = [];

        foreach ([
            [ServiceLog::TYPE_STDOUT, $service->log_path_out, 'log_offset_out'],
            [ServiceLog::TYPE_STDERR, $service->log_path_err, 'log_offset_err'],
        ] as [$type, $path, $offsetColumn]) {
            if (! $path || ! is_file($path)) {
                continue;
            }

            $result = $this->readNew($path, (int) $service->{$offsetColumn});

            if ($result === null) {
                continue;
            }

            $updates[$offsetColumn] = $result['offset'];

            if ($result['content'] === '') {
                continue;
            }

            $written += $this->store($service, $type, $result['content']);
        }

        if ($updates !== []) {
            $service->forceFill($updates)->saveQuietly();
        }

        if ($written > 0) {
            $this->trim($service);
        }

        return $written;
    }

    /**
     * Lê o que foi acrescentado ao arquivo desde `$offset`.
     *
     * Só consome até a última quebra de linha para não gravar uma linha pela
     * metade — o resto fica para a próxima passagem.
     *
     * @return array{content: string, offset: int}|null
     */
    protected function readNew(string $path, int $offset): ?array
    {
        clearstatcache(true, $path);

        $size = @filesize($path);

        if ($size === false) {
            return null;
        }

        // Arquivo truncado (rotação ou "limpar logs"): recomeça do início.
        if ($size < $offset) {
            $offset = 0;
        }

        if ($size === $offset) {
            return ['content' => '', 'offset' => $offset];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $limit = max(1024, (int) config('services_manager.logs.max_bytes_per_cycle', 262144));
        $length = min($limit, $size - $offset);

        fseek($handle, $offset);
        $chunk = (string) fread($handle, $length);
        fclose($handle);

        $read = strlen($chunk);

        if ($read === 0) {
            return ['content' => '', 'offset' => $offset];
        }

        $lastBreak = strrpos($chunk, "\n");

        if ($lastBreak === false) {
            // Nenhuma quebra: só consome se o pedaço já bateu no limite (linha
            // gigantesca), senão espera a linha terminar.
            if ($read < $limit) {
                return ['content' => '', 'offset' => $offset];
            }
        } else {
            $chunk = substr($chunk, 0, $lastBreak + 1);
            $read = $lastBreak + 1;
        }

        return ['content' => $chunk, 'offset' => $offset + $read];
    }

    /**
     * @return int  Linhas gravadas.
     */
    protected function store(Service $service, string $type, string $content): int
    {
        $maxLength = (int) config('services_manager.logs.max_line_length', 2000);
        $now = now();
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                continue;
            }

            $rows[] = [
                'service_id' => $service->getKey(),
                'type' => $type,
                'level' => $this->detectLevel($line, $type)->value,
                'message' => Str::limit($line, $maxLength, ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ServiceLog::query()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Deduz a gravidade da linha.
     *
     * O fluxo de origem sozinho não serve: o servidor embutido do PHP escreve
     * o log de acesso inteiro (inclusive respostas 200) no stderr. Por isso o
     * formato de acesso é reconhecido primeiro e classificado pelo status HTTP.
     */
    public function detectLevel(string $line, string $type): LogLevel
    {
        if (preg_match('/\[(\d{3})\]:/', $line, $m)) {
            $code = (int) $m[1];

            return match (true) {
                $code >= 500 => LogLevel::Error,
                $code >= 400 => LogLevel::Warning,
                default => LogLevel::Info,
            };
        }

        $upper = Str::upper($line);

        return match (true) {
            Str::contains($upper, ['CRITICAL', 'EMERGENCY', 'FATAL', 'PHP FATAL']) => LogLevel::Critical,
            Str::contains($upper, ['ERROR', 'EXCEPTION', 'FAILED', 'FALHA']) => LogLevel::Error,
            Str::contains($upper, ['WARNING', 'WARN', 'DEPRECATED']) => LogLevel::Warning,
            Str::contains($upper, ['DEBUG', 'TRACE']) => LogLevel::Debug,
            default => LogLevel::Info,
        };
    }

    /**
     * Mantém a tabela sob controle: guarda apenas as últimas N linhas por
     * serviço (um `queue:work` conversador enche isto em minutos).
     */
    protected function trim(Service $service): void
    {
        $max = (int) config('services_manager.logs.max_rows_per_service', 5000);

        if ($max <= 0) {
            return;
        }

        $count = $service->logs()->count();

        if ($count <= $max) {
            return;
        }

        $cutoff = $service->logs()
            ->orderByDesc('id')
            ->skip($max - 1)
            ->take(1)
            ->value('id');

        if ($cutoff) {
            $service->logs()->where('id', '<', $cutoff)->delete();
        }
    }
}
