<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\CompanionServiceFactory;
use App\Services\ProcessManager;
use App\Services\ServiceOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ServiceGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_starts_in_boot_order(): void
    {
        $group = ServiceGroup::factory()->create();

        $vite = Service::factory()->create([
            'name' => 'vite',
            'service_group_id' => $group->getKey(),
            'boot_order' => 2,
            'status' => ServiceStatus::Stopped,
        ]);
        $app = Service::factory()->create([
            'name' => 'app',
            'service_group_id' => $group->getKey(),
            'boot_order' => 0,
            'status' => ServiceStatus::Stopped,
        ]);
        $queue = Service::factory()->create([
            'name' => 'queue',
            'service_group_id' => $group->getKey(),
            'boot_order' => 1,
            'status' => ServiceStatus::Stopped,
        ]);

        $started = [];

        $this->mock(ProcessManager::class, function (MockInterface $mock) use (&$started): void {
            $mock->shouldReceive('start')->andReturnUsing(function (Service $service) use (&$started): array {
                $started[] = $service->name;

                return ['ok' => true, 'message' => 'ok'];
            });
        });

        $summary = app(ServiceOrchestrator::class)->startGroup($group->fresh());

        $this->assertSame(['app', 'queue', 'vite'], $started);
        $this->assertSame(3, $summary['ok']);
        $this->assertNotNull($app->fresh());
        $this->assertNotNull($queue->fresh());
        $this->assertNotNull($vite->fresh());
    }

    public function test_group_stops_in_reverse_order(): void
    {
        $group = ServiceGroup::factory()->create();

        Service::factory()->create([
            'name' => 'app',
            'service_group_id' => $group->getKey(),
            'boot_order' => 0,
            'status' => ServiceStatus::Running,
            'pid' => 111,
        ]);
        Service::factory()->create([
            'name' => 'queue',
            'service_group_id' => $group->getKey(),
            'boot_order' => 1,
            'status' => ServiceStatus::Running,
            'pid' => 222,
        ]);

        $stopped = [];

        $this->mock(ProcessManager::class, function (MockInterface $mock) use (&$stopped): void {
            $mock->shouldReceive('stop')->andReturnUsing(function (Service $service) use (&$stopped): array {
                $stopped[] = $service->name;

                return ['ok' => true, 'message' => 'ok'];
            });
        });

        app(ServiceOrchestrator::class)->stopGroup($group->fresh());

        $this->assertSame(['queue', 'app'], $stopped);
    }

    public function test_companions_are_created_in_the_same_group_and_after_the_source(): void
    {
        $source = Service::factory()->create([
            'name' => 'meu-projeto Web',
            'working_directory' => base_path(),
            'boot_order' => 0,
        ]);

        $this->mock(ProcessManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')->andReturn(['ok' => true, 'message' => 'ok']);
        });

        $result = app(CompanionServiceFactory::class)->create($source, ['queue', 'schedule']);

        $source->refresh();

        $this->assertSame(2, $result['created']);
        $this->assertNotNull($source->service_group_id);

        $members = $source->group->orderedServices()->pluck('name')->all();

        $this->assertSame(
            ['meu-projeto Web', 'meu-projeto Web — Fila', 'meu-projeto Web — Agendador'],
            $members,
        );
    }

    public function test_companions_are_not_duplicated(): void
    {
        $source = Service::factory()->create(['working_directory' => base_path()]);

        $this->mock(ProcessManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')->andReturn(['ok' => true, 'message' => 'ok']);
        });

        $factory = app(CompanionServiceFactory::class);

        $factory->create($source, ['queue']);
        $second = $factory->create($source->fresh(), ['queue']);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
