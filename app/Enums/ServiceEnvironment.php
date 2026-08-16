<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ServiceEnvironment: string implements HasLabel, HasColor, HasIcon
{
    case Development = 'development';
    case Staging = 'staging';
    case Production = 'production';
    case Testing = 'testing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Development => __('enums.service_environment.development'),
            self::Staging => __('enums.service_environment.staging'),
            self::Production => __('enums.service_environment.production'),
            self::Testing => __('enums.service_environment.testing'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Development => 'primary',
            self::Staging => 'warning',
            self::Production => 'danger',
            self::Testing => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Development => Heroicon::OutlinedWrenchScrewdriver,
            self::Staging => Heroicon::OutlinedRocketLaunch,
            self::Production => Heroicon::OutlinedServer,
            self::Testing => Heroicon::OutlinedBeaker,
        };
    }
}
