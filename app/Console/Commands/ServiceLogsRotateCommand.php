<?php

namespace App\Console\Commands;

use App\Services\LogIngestor;
use App\Services\LogRotator;
use App\Models\Service;
use Illuminate\Console\Command;

class ServiceLogsRotateCommand extends Command
{
    protected $signature = 'services:logs-rotate
        {--force : Ignora o intervalo mínimo entre rotações}';

    protected $description = 'Captura os logs pendentes, trunca arquivos grandes e apaga os órfãos antigos';

    public function handle(LogIngestor $ingestor, LogRotator $rotator): int
    {
        $lines = $ingestor->ingestMany(Service::query()->whereNotNull('log_path_out')->get());

        $result = $this->option('force') ? $rotator->rotate() : $rotator->rotateIfDue();

        if ($result === null) {
            $this->line('Rotação ignorada: ainda dentro do intervalo mínimo. Use --force para forçar.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d linhas capturadas, %d arquivo(s) truncado(s), %d apagado(s), %d KB liberados.',
            $lines,
            $result['rotated'],
            $result['deleted'],
            $result['freed_kb'],
        ));

        return self::SUCCESS;
    }
}
