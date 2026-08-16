<?php

namespace Tests\Feature;

use App\Enums\HealthStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use App\Services\HealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function service(array $attributes = []): Service
    {
        return Service::factory()->create(array_merge([
            'command' => 'php artisan serve --host=127.0.0.1 --port=8123',
            'port' => 8123,
            'status' => ServiceStatus::Running,
            'pid' => 4242,
            'health_check_enabled' => true,
            'health_check_path' => '/up',
            'health_check_status' => 200,
            'health_check_timeout' => 2,
        ], $attributes));
    }

    public function test_expected_status_marks_the_service_as_healthy(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $service = $this->service();

        $this->assertSame(HealthStatus::Ok, app(HealthChecker::class)->check($service, force: true));

        $service->refresh();

        $this->assertSame(200, $service->health_last_code);
        $this->assertSame(0, $service->health_failures);
        $this->assertNull($service->health_error);
    }

    /**
     * O ponto do health check: a porta pode estar aberta e a aplicação
     * respondendo 500 — para o check de porta isso é "no ar".
     */
    public function test_open_port_answering_500_is_not_healthy(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $service = $this->service(['port_open' => true]);
        $checker = app(HealthChecker::class);

        $this->assertSame(HealthStatus::Degraded, $checker->check($service, force: true));

        $service->refresh();
        $this->assertSame(1, $service->health_failures);
        $this->assertStringContainsString('500', (string) $service->health_error);
    }

    public function test_failing_only_after_the_configured_threshold_and_alerts_once(): void
    {
        config(['services_manager.health.failure_threshold' => 3]);

        Http::fake(['*' => Http::response('boom', 503)]);

        User::factory()->create();
        $service = $this->service();
        $checker = app(HealthChecker::class);

        $this->assertSame(HealthStatus::Degraded, $checker->check($service->fresh(), force: true));
        $this->assertSame(HealthStatus::Degraded, $checker->check($service->fresh(), force: true));
        $this->assertSame(HealthStatus::Failing, $checker->check($service->fresh(), force: true));

        // Continua falhando, mas o alerta não se repete dentro do cooldown.
        $checker->check($service->fresh(), force: true);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_check_respects_the_interval_unless_forced(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        config(['services_manager.health.interval' => 30]);

        $service = $this->service(['health_checked_at' => now()->subSeconds(5)]);

        $this->assertNull(app(HealthChecker::class)->check($service));

        Http::assertNothingSent();
    }

    public function test_disabled_or_stopped_services_are_never_checked(): void
    {
        Http::fake();

        $disabled = $this->service(['health_check_enabled' => false]);
        $stopped = $this->service(['status' => ServiceStatus::Stopped, 'pid' => null]);

        $checker = app(HealthChecker::class);

        $this->assertNull($checker->check($disabled, force: true));
        $this->assertNull($checker->check($stopped, force: true));

        Http::assertNothingSent();
    }
}
