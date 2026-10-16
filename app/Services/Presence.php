<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;

final readonly class Presence
{
    /**
     * A user counts as online while their last request is at most this recent.
     */
    public const int ONLINE_WINDOW_MINUTES = 5;

    /**
     * The timestamp is only rewritten once it is this stale, so a busy user costs at most one write per refresh.
     * It stays below the online window, so someone active never flickers offline between writes.
     */
    public const int REFRESH_AFTER_MINUTES = 4;

    public function isOnline(?CarbonInterface $lastActivityAt): bool
    {
        return $lastActivityAt instanceof CarbonInterface
            && $lastActivityAt->gt(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    public function needsRefresh(?CarbonInterface $lastActivityAt): bool
    {
        return ! $lastActivityAt instanceof CarbonInterface
            || $lastActivityAt->lte(now()->subMinutes(self::REFRESH_AFTER_MINUTES));
    }
}
