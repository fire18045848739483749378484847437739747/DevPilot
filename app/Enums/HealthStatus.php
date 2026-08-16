<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Resultado do health check HTTP do serviço.
 *
 * `Degraded` é o estado intermediário: já houve falha, mas ainda não o
 * suficiente para disparar alerta (ver `services_manager.health.failure_threshold`).
 */
enum HealthStatus: string implements HasLabel, HasColor, HasIcon
{
    case Unknown = 'unknown';
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failing = 'failing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unknown => __('enums.health_status.unknown'),
            self::Ok => __('enums.health_status.ok'),
            self::Degraded => __('enums.health_status.degraded'),
            self::Failing => __('enums.health_status.failing'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Ok => 'success',
            self::Degraded => 'warning',
            self::Failing => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Unknown => Heroicon::OutlinedQuestionMarkCircle,
            self::Ok => Heroicon::OutlinedHeart,
            self::Degraded => Heroicon::OutlinedExclamationTriangle,
            self::Failing => Heroicon::OutlinedXCircle,
        };
    }
}
