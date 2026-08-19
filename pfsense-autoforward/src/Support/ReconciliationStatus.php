<?php

namespace Chr0mX\PfSenseAutoForward\Support;

use Chr0mX\PfSenseAutoForward\DTO\ReconciliationSummary;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed last-run status, read by the admin page (polling, same
 * pattern as ProgressReporter in the Valheim Mod Manager plugin) so both
 * the scheduled reconciliation and the "Sync Now" button feed one shared
 * status display.
 */
final class ReconciliationStatus
{
    private const CACHE_KEY = 'pfsense-autoforward:status';

    private const TTL_SECONDS = 86400;

    public static function markRunning(): void
    {
        $current = self::raw();

        Cache::put(self::CACHE_KEY, [
            'state' => 'running',
            'summary' => $current['summary'] ?? null,
            'ran_at' => $current['ran_at'] ?? null,
        ], self::TTL_SECONDS);
    }

    public static function markComplete(ReconciliationSummary $summary): void
    {
        Cache::put(self::CACHE_KEY, [
            'state' => $summary->hasFailures() ? 'failed' : 'success',
            'summary' => $summary->toArray(),
            'ran_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{state: string, summary: array<string, mixed>|null, ran_at: string|null}
     */
    public static function get(): array
    {
        return self::raw() ?? ['state' => 'idle', 'summary' => null, 'ran_at' => null];
    }

    /**
     * @return array{state: string, summary: array<string, mixed>|null, ran_at: string|null}|null
     */
    private static function raw(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }
}
