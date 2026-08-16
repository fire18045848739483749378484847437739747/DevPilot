<?php

namespace Database\Factories;

use App\Enums\RestartPolicy;
use App\Enums\ServiceEnvironment;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'command' => 'php artisan serve --host=127.0.0.1 --port=' . $this->faker->numberBetween(8000, 9000),
            'working_directory' => base_path(),
            'user' => null,
            'environment' => ServiceEnvironment::Development,
            'port' => $this->faker->numberBetween(8000, 9000),
            'env_vars' => null,
            'auto_start_on_boot' => false,
            'auto_restart' => false,
            'restart_policy' => RestartPolicy::Manual,
            'max_restarts' => 5,
            'stop_timeout' => 10,
            'status' => ServiceStatus::Stopped,
            'pid' => null,
            'exit_code' => null,
            'started_at' => null,
            'cpu_usage' => null,
            'memory_kb' => null,
            'uptime_seconds' => null,
            'port_open' => null,
            'last_metrics_at' => null,
            'last_restart_at' => null,
            'restart_count' => 0,
            'last_error' => null,
            'log_path_out' => null,
            'log_path_err' => null,
            // Espelham os defaults do banco: `create()` devolve o model só com
            // o que foi atribuído, então sem isto os campos ficam nulos aqui e
            // o comportamento do teste diverge do da aplicação.
            'service_group_id' => null,
            'boot_order' => 0,
            'log_offset_out' => 0,
            'log_offset_err' => 0,
            'health_check_enabled' => false,
            'health_check_status' => 200,
            'health_check_timeout' => 5,
            'health_failures' => 0,
            'alerts_enabled' => true,
            'alert_cpu_threshold' => null,
            'alert_memory_threshold_mb' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => ServiceStatus::Running,
            'pid' => $this->faker->numberBetween(1000, 99999),
            'started_at' => now()->subMinutes($this->faker->numberBetween(1, 600)),
            'uptime_seconds' => $this->faker->numberBetween(60, 36000),
            'cpu_usage' => $this->faker->randomFloat(1, 0, 90),
            'memory_kb' => $this->faker->numberBetween(20_000, 800_000),
        ]);
    }
}
