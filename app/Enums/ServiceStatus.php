<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ServiceStatus: string implements HasLabel, HasColor, HasIcon
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Starting = 'starting';
    case Restarting = 'restarting';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => __('enums.service_status.running'),
            self::Stopped => __('enums.service_status.stopped'),
            self::Starting => __('enums.service_status.starting'),
            self::Restarting => __('enums.service_status.restarting'),
            self::Error => __('enums.service_status.error'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Stopped => 'gray',
            self::Starting => 'warning',
            self::Restarting => 'info',
            self::Error => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Running => Heroicon::OutlinedCheckCircle,
            self::Stopped => Heroicon::OutlinedStopCircle,
            self::Starting => Heroicon::OutlinedClock,
            self::Restarting => Heroicon::OutlinedArrowPath,
            self::Error => Heroicon::OutlinedExclamationTriangle,
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Running, self::Starting, self::Restarting], true);
    }
}
