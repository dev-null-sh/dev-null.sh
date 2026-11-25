<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How long a failed push waits before its phones are tried again. Google and Apple ask for a wait of their own when
 * they are busy; without one, each attempt waits twice as long as the one before: 30 seconds, then 60.
 */
final class PushRetryDelay
{
    private const int FIRST_SECONDS = 30;

    public static function secondsFor(int $attempt, ?int $retryAfterSeconds): int
    {
        return max(self::FIRST_SECONDS * 2 ** max(0, $attempt - 1), $retryAfterSeconds ?? 0);
    }
}
