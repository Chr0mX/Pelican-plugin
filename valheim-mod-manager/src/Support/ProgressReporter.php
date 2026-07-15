<?php

namespace Chr0mX\ValheimModManager\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tiny cache-backed progress tracker so the Filament page can poll
 * (`wire:poll`) and show "Downloading... / Extracting... / Installing...
 * / Finished" while a queued job runs, without needing websockets or a
 * dedicated HTTP route.
 */
final class ProgressReporter
{
    private const TTL_SECONDS = 600;

    public static function report(string $token, string $stage, ?string $error = null): void
    {
        Cache::put(self::cacheKey($token), [
            'stage' => $stage,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{stage: string, error: ?string, updated_at: string}|null
     */
    public static function get(string $token): ?array
    {
        return Cache::get(self::cacheKey($token));
    }

    public static function forget(string $token): void
    {
        Cache::forget(self::cacheKey($token));
    }

    private static function cacheKey(string $token): string
    {
        return "valheim-mod-manager:progress:$token";
    }
}
