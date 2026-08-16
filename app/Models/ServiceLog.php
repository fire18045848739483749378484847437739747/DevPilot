<?php

namespace App\Models;

use App\Enums\LogLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'type', 'level', 'message'])]
class ServiceLog extends Model
{
    protected $table = 'service_logs';

    public const TYPE_STDOUT = 'stdout';
    public const TYPE_STDERR = 'stderr';
    public const TYPE_SYSTEM = 'system';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
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
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_STDOUT => __('services.log_types.stdout'),
            self::TYPE_STDERR => __('services.log_types.stderr'),
            self::TYPE_SYSTEM => __('services.log_types.system'),
        ];
    }
}
