<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Models\Service;
use App\Models\ServiceLog;
use App\Services\LogIngestor;
use App\Services\LogRotator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogIngestorTest extends TestCase
{
    use RefreshDatabase;

    protected string $out;

    protected string $err;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = storage_path('logs/services');
        File::ensureDirectoryExists($dir);

        $this->out = $dir . DIRECTORY_SEPARATOR . 'test-ingest.out.log';
        $this->err = $dir . DIRECTORY_SEPARATOR . 'test-ingest.err.log';

        File::put($this->out, '');
        File::put($this->err, '');
    }

    protected function tearDown(): void
    {
        File::delete([$this->out, $this->err]);

        parent::tearDown();
    }

    protected function service(): Service
    {
        return Service::factory()->create([
            'log_path_out' => $this->out,
            'log_path_err' => $this->err,
        ]);
    }

    public function test_only_new_bytes_are_ingested(): void
    {
        $service = $this->service();
        $ingestor = app(LogIngestor::class);

        File::put($this->out, "primeira linha\nsegunda linha\n");

        $this->assertSame(2, $ingestor->ingest($service));
        $this->assertSame(2, $service->logs()->count());

        // Nada novo no arquivo: nenhuma linha duplicada.
        $this->assertSame(0, $ingestor->ingest($service->fresh()));
        $this->assertSame(2, $service->logs()->count());

        File::append($this->out, "terceira linha\n");

        $this->assertSame(1, $ingestor->ingest($service->fresh()));
        $this->assertSame(3, $service->logs()->count());
    }

    public function test_partial_line_waits_for_the_line_break(): void
    {
        $service = $this->service();
        $ingestor = app(LogIngestor::class);

        File::put($this->out, "linha completa\nlinha pela metade");

        $this->assertSame(1, $ingestor->ingest($service));

        File::append($this->out, " agora inteira\n");

        $this->assertSame(1, $ingestor->ingest($service->fresh()));
        $this->assertSame(
            'linha pela metade agora inteira',
            $service->logs()->orderByDesc('id')->value('message'),
        );
    }

    /**
     * A detecção de truncamento é por tamanho: arquivo menor que o offset
     * significa que ele foi zerado por baixo dos panos. Quem trunca de verdade
     * (LogRotator e "limpar logs") também zera o offset, então este é só o
     * cinto de segurança.
     */
    public function test_shrunk_file_restarts_from_the_beginning(): void
    {
        $service = $this->service();
        $ingestor = app(LogIngestor::class);

        File::put($this->out, "uma linha bem longa antes da rotacao\n");
        $ingestor->ingest($service);

        File::put($this->out, "depois\n");

        $this->assertSame(1, $ingestor->ingest($service->fresh()));
        $this->assertSame(
            'depois',
            $service->logs()->orderByDesc('id')->value('message'),
        );
    }

    public function test_access_log_status_defines_the_level_even_on_stderr(): void
    {
        $ingestor = app(LogIngestor::class);

        $this->assertSame(
            LogLevel::Info,
            $ingestor->detectLevel('[Fri Aug 15 10:00:00 2026] 127.0.0.1:5555 [200]: GET /', ServiceLog::TYPE_STDERR),
        );

        $this->assertSame(
            LogLevel::Warning,
            $ingestor->detectLevel('[Fri Aug 15 10:00:00 2026] 127.0.0.1:5555 [404]: GET /nada', ServiceLog::TYPE_STDERR),
        );

        $this->assertSame(
            LogLevel::Error,
            $ingestor->detectLevel('[Fri Aug 15 10:00:00 2026] 127.0.0.1:5555 [500]: GET /erro', ServiceLog::TYPE_STDERR),
        );

        $this->assertSame(
            LogLevel::Critical,
            $ingestor->detectLevel('PHP Fatal error: algo explodiu', ServiceLog::TYPE_STDERR),
        );
    }

    public function test_rotation_truncates_large_files_and_resets_the_offset(): void
    {
        config(['services_manager.logs.rotate_size_mb' => 1]);

        $service = $this->service();

        File::put($this->out, str_repeat("linha de log para encher o arquivo\n", 40_000));

        $this->assertGreaterThan(1024 * 1024, filesize($this->out));

        $result = app(LogRotator::class)->rotate();

        $this->assertSame(1, $result['rotated']);
        $this->assertSame(0, filesize($this->out));
        $this->assertSame(0, (int) $service->fresh()->log_offset_out);
    }
}
