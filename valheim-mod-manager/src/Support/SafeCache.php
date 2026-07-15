<?php

namespace Chr0mX\ValheimModManager\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A misconfigured cache backend (e.g. the "file" driver pointed at a
 * storage/framework/cache/data directory that doesn't exist or isn't
 * writable) throws instead of degrading, which would otherwise blank the
 * entire Mods page. Every cache interaction in this plugin goes through
 * here so a broken cache degrades to "just do the work every time" instead
 * of a hard failure.
 */
final class SafeCache
{
    public static function remember(string $key, mixed $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (Throwable $exception) {
            report($exception);

            return $callback();
        }
    }

    public static function lock(string $key, int $seconds, int $waitSeconds, callable $callback): mixed
    {
        try {
            return Cache::lock($key, $seconds)->block($waitSeconds, $callback);
        } catch (Throwable $exception) {
            report($exception);

            // Best effort: proceed without mutual exclusion rather than
            // failing the whole install/update/remove/toggle operation.
            return $callback();
        }
    }
}
