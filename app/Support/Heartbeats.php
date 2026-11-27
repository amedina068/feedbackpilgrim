<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The last run of the scheduler and of the queue worker.
 *
 * The scheduler records its heartbeat each minute and queues one small job
 * that records the heartbeat of the worker. `fp:status` and `fp:doctor`
 * read both values from the cache.
 */
class Heartbeats
{
    public const SCHEDULER_KEY = 'fp:heartbeat:scheduler';

    public const QUEUE_KEY = 'fp:heartbeat:queue';

    /** A heartbeat older than this is stale. */
    public const STALE_MINUTES = 5;

    public static function recordScheduler(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->toIso8601String());
    }

    public static function recordQueue(): void
    {
        Cache::forever(self::QUEUE_KEY, now()->toIso8601String());
    }

    public static function scheduler(): ?CarbonInterface
    {
        return self::read(self::SCHEDULER_KEY);
    }

    public static function queue(): ?CarbonInterface
    {
        return self::read(self::QUEUE_KEY);
    }

    public static function isFresh(?CarbonInterface $at): bool
    {
        return $at !== null && $at->greaterThan(now()->subMinutes(self::STALE_MINUTES));
    }

    private static function read(string $key): ?CarbonInterface
    {
        try {
            $value = Cache::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? Carbon::parse($value) : null;
    }
}
