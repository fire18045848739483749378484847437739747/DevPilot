<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amostra histórica de métricas de um serviço, usada nos gráficos do painel.
 */
#[Fillable(['service_id', 'cpu_usage', 'memory_kb', 'port_open', 'recorded_at'])]
class ServiceMetric extends Model
{
    protected $table = 'service_metrics';

    public $timestamps = false;

    /** Intervalo mínimo entre amostras gravadas, em segundos. */
    public const SAMPLE_INTERVAL = 15;

    /** Janela de retenção do histórico, em horas. */
    public const RETENTION_HOURS = 24;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cpu_usage' => 'float',
            'memory_kb' => 'integer',
            'port_open' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Grava uma amostra, respeitando SAMPLE_INTERVAL para não inflar a tabela
     * com o polling de 3s dos widgets.
     */
    public static function record(Service $service): void
    {
        if ($service->cpu_usage === null) {
            return;
        }

        $last = static::query()
            ->where('service_id', $service->getKey())
            ->latest('recorded_at')
            ->first();

        if ($last && $last->recorded_at->diffInSeconds(now()) < self::SAMPLE_INTERVAL) {
            return;
        }

        static::query()->create([
            'service_id' => $service->getKey(),
            'cpu_usage' => $service->cpu_usage,
            'memory_kb' => $service->memory_kb,
            'port_open' => $service->port_open,
            'recorded_at' => now(),
        ]);

        static::query()
            ->where('service_id', $service->getKey())
            ->where('recorded_at', '<', now()->subHours(self::RETENTION_HOURS))
            ->delete();
    }
}
