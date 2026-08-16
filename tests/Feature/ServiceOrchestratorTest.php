<?php

namespace Tests\Feature;

use App\Enums\ServiceEnvironment;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\ProcessManager;
use App\Services\ServiceOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * O ProcessManager é mockado: as operações em lote são testadas pela lógica de
 * seleção/resumo, sem realmente abrir processos do PowerShell.
 */
class ServiceOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_services_are_never_touched_in_bulk(): void
    {
        Service::factory()->create([
            'name' => 'prod',
            'environment' => ServiceEnvironment::Production,
            'status' => ServiceStatus::Stopped,
        ]);
        Service::factory()->create([
            'name' => 'dev',
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Stopped,
        ]);

        $candidates = app(ServiceOrchestrator::class)->candidates();

        $this->assertSame(['dev'], $candidates->pluck('name')->all());
    }

    public function test_start_all_starts_only_stopped_services(): void
    {
        $stopped = Service::factory()->create([
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Stopped,
        ]);
        Service::factory()->create([
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Running,
            'pid' => 4242,
        ]);

        $started = [];

        $this->mock(ProcessManager::class, function (MockInterface $mock) use (&$started): void {
            $mock->shouldReceive('start')
                ->andReturnUsing(function (Service $service) use (&$started): array {
                    $started[] = $service->getKey();

                    return ['ok' => true, 'message' => 'ok'];
                });
        });

        $summary = app(ServiceOrchestrator::class)->startAll();

        $this->assertSame([$stopped->getKey()], $started);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(1, $summary['skipped']);
    }

    public function test_stop_all_stops_only_running_services(): void
    {
        $running = Service::factory()->create([
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Running,
            'pid' => 1234,
        ]);
        Service::factory()->create([
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Stopped,
        ]);

        $stopped = [];

        $this->mock(ProcessManager::class, function (MockInterface $mock) use (&$stopped): void {
            $mock->shouldReceive('stop')
                ->andReturnUsing(function (Service $service) use (&$stopped): array {
                    $stopped[] = $service->getKey();

                    return ['ok' => true, 'message' => 'ok'];
                });
        });

        $summary = app(ServiceOrchestrator::class)->stopAll();

        $this->assertSame([$running->getKey()], $stopped);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame(1, $summary['skipped']);
    }

    public function test_failures_are_reported_with_service_name(): void
    {
        Service::factory()->create([
            'name' => 'quebrado',
            'environment' => ServiceEnvironment::Development,
            'status' => ServiceStatus::Stopped,
        ]);

        $this->mock(ProcessManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')
                ->andReturn(['ok' => false, 'message' => 'porta em uso']);
        });

        $summary = app(ServiceOrchestrator::class)->startAll();

        $this->assertSame(0, $summary['ok']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(['quebrado: porta em uso'], $summary['errors']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
