<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use App\Services\AlertManager;
use App\Services\MetricsCollector;
use App\Services\ProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
    }

    public function test_the_same_alert_is_not_repeated_within_the_cooldown(): void
    {
        $service = Service::factory()->create();
        $alerts = app(AlertManager::class);

        $alerts->serviceDown($service, 'caiu');
        $alerts->serviceDown($service, 'caiu de novo');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_alerts_can_be_disabled_per_service(): void
    {
        $service = Service::factory()->create(['alerts_enabled' => false]);

        app(AlertManager::class)->serviceDown($service, 'caiu');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_resetting_allows_the_alert_to_fire_again(): void
    {
        $service = Service::factory()->create();
        $alerts = app(AlertManager::class);

        $alerts->serviceDown($service, 'caiu');
        $alerts->reset($service);
        $alerts->serviceDown($service, 'caiu outra vez');

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_metrics_collection_alerts_when_cpu_and_memory_cross_the_limits(): void
    {
        $service = Service::factory()->create([
            'status' => ServiceStatus::Running,
            'pid' => 4242,
            'alert_cpu_threshold' => 50,
            'alert_memory_threshold_mb' => 100,
            // Base para o cálculo de CPU% por delta.
            'cpu_seconds_total' => 0.0,
            'last_metrics_at' => now()->subSeconds(10),
        ]);

        $this->mock(ProcessManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('probe')->andReturn([
                // 9 s de CPU em 10 s de relógio ≈ 90%.
                'procs' => [4242 => ['cpu_seconds' => 9.0, 'memory_kb' => 300 * 1024]],
                'ports' => [],
            ]);
        });

        app(MetricsCollector::class)->collect([$service->fresh()], force: true);

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_no_alert_when_no_threshold_is_configured(): void
    {
        $service = Service::factory()->create([
            'status' => ServiceStatus::Running,
            'pid' => 4242,
            'alert_cpu_threshold' => null,
            'alert_memory_threshold_mb' => null,
            'cpu_seconds_total' => 0.0,
            'last_metrics_at' => now()->subSeconds(10),
        ]);

        $this->mock(ProcessManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('probe')->andReturn([
                'procs' => [4242 => ['cpu_seconds' => 9.0, 'memory_kb' => 900 * 1024]],
                'ports' => [],
            ]);
        });

        app(MetricsCollector::class)->collect([$service->fresh()], force: true);

        $this->assertDatabaseCount('notifications', 0);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
