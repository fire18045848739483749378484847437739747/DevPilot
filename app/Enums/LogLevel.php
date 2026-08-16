<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Gravidade de uma linha de log capturada do stdout/stderr do serviço.
 *
 * Não se confunde com `ServiceLog::TYPE_*`, que diz apenas de qual fluxo a
 * linha veio — o `php -S`, por exemplo, escreve o log de acesso (200 incluso)
 * no stderr.
 */
enum LogLevel: string implements HasLabel, HasColor, HasIcon
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Debug => __('enums.log_level.debug'),
            self::Info => __('enums.log_level.info'),
            self::Warning => __('enums.log_level.warning'),
            self::Error => __('enums.log_level.error'),
            self::Critical => __('enums.log_level.critical'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Debug => 'gray',
            self::Info => 'info',
            self::Warning => 'warning',
            self::Error => 'danger',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum
    {
        return match ($this) {
            self::Debug => Heroicon::OutlinedBugAnt,
            self::Info => Heroicon::OutlinedInformationCircle,
            self::Warning => Heroicon::OutlinedExclamationTriangle,
            self::Error => Heroicon::OutlinedXCircle,
            self::Critical => Heroicon::OutlinedFire,
        };
    }
}
