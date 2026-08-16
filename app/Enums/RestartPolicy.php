<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RestartPolicy: string implements HasLabel, HasColor, HasIcon
{
    case Always = 'always';
    case OnFailure = 'on_failure';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Always => __('enums.restart_policy.always'),
            self::OnFailure => __('enums.restart_policy.on_failure'),
            self::Manual => __('enums.restart_policy.manual'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Always => 'primary',
            self::OnFailure => 'warning',
            self::Manual => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Always => Heroicon::OutlinedArrowPathRoundedSquare,
            self::OnFailure => Heroicon::OutlinedLifebuoy,
            self::Manual => Heroicon::OutlinedHandRaised,
        };
    }
}
