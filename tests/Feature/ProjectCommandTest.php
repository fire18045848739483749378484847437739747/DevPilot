<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\ProjectCommandCatalog;
use App\Services\ProjectCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $plainDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // O próprio Gerenciador é um projeto Laravel + Filament válido, e fica
        // dentro da raiz permitida — serve de alvo para os testes.
        config(['services_manager.dev_roots' => [dirname(base_path())]]);

        $this->plainDirectory = storage_path('framework/testing/pasta-simples');
        File::ensureDirectoryExists($this->plainDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->plainDirectory);

        parent::tearDown();
    }

    public function test_capabilities_are_detected_from_the_directory(): void
    {
        $catalog = app(ProjectCommandCatalog::class);

        $capabilities = $catalog->capabilities(base_path());

        $this->assertContains('laravel', $capabilities);
        $this->assertContains('composer', $capabilities);
        $this->assertContains('filament', $capabilities);

        $this->assertSame([], $catalog->capabilities($this->plainDirectory));
        $this->assertSame([], $catalog->capabilities(null));
    }

    public function test_commands_are_filtered_by_what_the_directory_supports(): void
    {
        $catalog = app(ProjectCommandCatalog::class);

        $this->assertNotSame([], $catalog->forDirectory(base_path()));
        $this->assertSame([], $catalog->forDirectory($this->plainDirectory));
    }

    public function test_destructive_commands_are_hidden_by_default(): void
    {
        $catalog = app(ProjectCommandCatalog::class);

        $safe = $this->flatten($catalog->forDirectory(base_path()));
        $withDangerous = $this->flatten($catalog->forDirectory(base_path(), includeDangerous: true));

        $this->assertNotContains('migrate-fresh-seed', array_column($safe, 'key'));
        $this->assertContains('migrate-fresh-seed', array_column($withDangerous, 'key'));
    }

    /**
     * O painel não pode virar um shell no disco inteiro: só roda dentro das
     * raízes de `DEV_ROOTS`.
     */
    public function test_directories_outside_the_allowed_roots_are_refused(): void
    {
        config(['services_manager.dev_roots' => [storage_path('framework')]]);

        $result = app(ProjectCommandRunner::class)->run('about', base_path());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('DEV_ROOTS', $result['output']);
    }

    public function test_commands_are_refused_when_the_project_does_not_support_them(): void
    {
        $result = app(ProjectCommandRunner::class)->run('about', $this->plainDirectory);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Laravel', $result['output']);
    }

    public function test_unknown_command_keys_are_refused(): void
    {
        $result = app(ProjectCommandRunner::class)->run('rm-rf', base_path());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('rm-rf', $result['output']);
    }

    /**
     * Execução de verdade: prova que o runner resolve o PHP, roda na pasta
     * certa e devolve a saída. `about` é somente leitura.
     */
    public function test_a_real_command_runs_and_captures_the_output(): void
    {
        $result = app(ProjectCommandRunner::class)->run('about', base_path());

        $this->assertTrue($result['ok'], 'saída: ' . $result['output']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Environment', $result['output']);
    }

    public function test_the_run_is_recorded_in_the_logs_of_services_in_that_folder(): void
    {
        $service = Service::factory()->create(['working_directory' => base_path()]);

        app(ProjectCommandRunner::class)->run('about', base_path());

        $log = $service->logs()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('php artisan about', $log->message);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return list<array<string, mixed>>
     */
    protected function flatten(array $grouped): array
    {
        return array_merge(...array_values($grouped ?: [[]]));
    }
}
