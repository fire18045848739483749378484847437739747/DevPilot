<?php

namespace App\Models;

use App\Enums\HealthStatus;
use App\Enums\RestartPolicy;
use App\Enums\ServiceEnvironment;
use App\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'service_group_id',
    'boot_order',
    'description',
    'command',
    'working_directory',
    'user',
    'environment',
    'port',
    'env_vars',
    'auto_start_on_boot',
    'auto_restart',
    'restart_policy',
    'max_restarts',
    'stop_timeout',
    'status',
    'pid',
    'exit_code',
    'started_at',
    'cpu_usage',
    'cpu_seconds_total',
    'memory_kb',
    'uptime_seconds',
    'port_open',
    'health_check_enabled',
    'health_check_path',
    'health_check_status',
    'health_check_timeout',
    'health_status',
    'health_last_code',
    'health_error',
    'health_failures',
    'health_checked_at',
    'alerts_enabled',
    'alert_cpu_threshold',
    'alert_memory_threshold_mb',
    'url_scheme',
    'url_path',
    'last_metrics_at',
    'last_restart_at',
    'restart_count',
    'last_error',
    'log_path_out',
    'log_path_err',
    'log_offset_out',
    'log_offset_err',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $table = 'services';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => ServiceEnvironment::class,
            'restart_policy' => RestartPolicy::class,
            'status' => ServiceStatus::class,
            'health_status' => HealthStatus::class,
            'env_vars' => 'array',
            'auto_start_on_boot' => 'boolean',
            'auto_restart' => 'boolean',
            'port' => 'integer',
            'max_restarts' => 'integer',
            'stop_timeout' => 'integer',
            'pid' => 'integer',
            'exit_code' => 'integer',
            'cpu_usage' => 'float',
            'cpu_seconds_total' => 'float',
            'memory_kb' => 'integer',
            'uptime_seconds' => 'integer',
            'port_open' => 'boolean',
            'restart_count' => 'integer',
            'boot_order' => 'integer',
            'health_check_enabled' => 'boolean',
            'health_check_status' => 'integer',
            'health_check_timeout' => 'integer',
            'health_last_code' => 'integer',
            'health_failures' => 'integer',
            'alerts_enabled' => 'boolean',
            'alert_cpu_threshold' => 'float',
            'alert_memory_threshold_mb' => 'integer',
            'log_offset_out' => 'integer',
            'log_offset_err' => 'integer',
            'started_at' => 'datetime',
            'last_metrics_at' => 'datetime',
            'last_restart_at' => 'datetime',
            'health_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class, 'service_group_id');
    }

    /**
     * @return HasMany<ServiceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    /**
     * @return HasMany<ServiceMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(ServiceMetric::class);
    }

    /**
     * Host de acesso, extraído do comando (`--host=`, `-S host:porta`).
     * `0.0.0.0` vira `127.0.0.1` para que o link seja clicável.
     */
    public function resolvedHost(): string
    {
        $command = $this->command ?? '';
        $host = null;

        if (preg_match('/--host[= ]([^\s"\']+)/i', $command, $m)) {
            $host = $m[1];
        } elseif (preg_match('/-S\s+([^\s:"\']+):\d+/i', $command, $m)) {
            $host = $m[1];
        }

        $host ??= '127.0.0.1';

        return in_array($host, ['0.0.0.0', '::', '[::]'], true) ? '127.0.0.1' : $host;
    }

    /**
     * Porta de acesso: a coluna `port` tem prioridade; senão tenta o comando.
     */
    public function resolvedPort(): ?int
    {
        if ($this->port) {
            return $this->port;
        }

        $command = $this->command ?? '';

        if (preg_match('/--port[= ](\d+)/i', $command, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/-S\s+[^\s:"\']+:(\d+)/i', $command, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * URL completa de acesso ao serviço, ou null se não houver porta conhecida.
     */
    public function accessUrl(): ?string
    {
        $port = $this->resolvedPort();

        if (! $port) {
            return null;
        }

        $scheme = $this->url_scheme ?: 'http';
        $path = '/' . ltrim((string) ($this->url_path ?? ''), '/');

        return rtrim($scheme . '://' . $this->resolvedHost() . ':' . $port . $path, '/');
    }

    /**
     * URL usada pelo health check HTTP. Usa `health_check_path` quando definido
     * e cai no `url_path` do link de acesso quando não.
     */
    public function healthUrl(): ?string
    {
        $port = $this->resolvedPort();

        if (! $port) {
            return null;
        }

        $path = $this->health_check_path ?? $this->url_path;

        return ($this->url_scheme ?: 'http')
            . '://' . $this->resolvedHost()
            . ':' . $port
            . '/' . ltrim((string) $path, '/');
    }

    /**
     * O health check só faz sentido com o processo no ar e uma URL conhecida.
     */
    public function healthCheckable(): bool
    {
        return $this->health_check_enabled
            && $this->isRunning()
            && $this->healthUrl() !== null;
    }

    public function isRunning(): bool
    {
        return $this->status === ServiceStatus::Running && $this->pid !== null;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [ServiceStatus::Starting, ServiceStatus::Restarting], true);
    }

    public function isStopped(): bool
    {
        return $this->status === ServiceStatus::Stopped;
    }

    public function isError(): bool
    {
        return $this->status === ServiceStatus::Error;
    }

    public function updateSlug(): void
    {
        $base = Str::slug($this->name);
        $slug = $base;
        $i = 2;

        while (self::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base . '-' . $i++;
        }

        $this->slug = $slug;
    }
}
