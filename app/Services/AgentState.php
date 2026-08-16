<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class AgentState
{
    public static function heartbeatPath(): string
    {
        return storage_path('app/services/agent.heartbeat');
    }

    public static function pidPath(): string
    {
        return storage_path('app/services/agent.pid');
    }

    public static function ping(): void
    {
        File::ensureDirectoryExists(storage_path('app/services'));
        File::put(self::heartbeatPath(), now()->toIso8601String());
    }

    public static function isRunning(int $threshold = 20): bool
    {
        if (! File::exists(self::heartbeatPath())) {
            return false;
        }

        return now()->diffInSeconds(self::lastHeartbeat() ?? now()) <= $threshold;
    }

    public static function lastHeartbeat(): ?Carbon
    {
        if (! File::exists(self::heartbeatPath())) {
            return null;
        }

        return Carbon::createFromTimestamp(File::lastModified(self::heartbeatPath()));
    }

    public static function register(): void
    {
        File::ensureDirectoryExists(storage_path('app/services'));
        File::put(self::pidPath(), (string) getmypid());
        self::ping();
    }

    public static function unregister(): void
    {
        File::delete(self::pidPath());
    }
}
