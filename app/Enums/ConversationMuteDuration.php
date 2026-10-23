<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How long a remote stays muted for the user who muted it.
 */
enum ConversationMuteDuration: string
{
    case Hour = 'hour';
    case Tomorrow = 'tomorrow';
    case Week = 'week';
    case Forever = 'forever';
    case Off = 'off';

    /**
     * When the mute ends, to the second the column keeps: forever is the far end of the calendar, and off is no
     * mute at all.
     */
    public function until(?string $timezone = null): ?CarbonImmutable
    {
        return match ($this) {
            self::Hour => CarbonImmutable::now()->startOfSecond()->addHour(),
            self::Tomorrow => CarbonImmutable::now($timezone ?? 'UTC')->addDay()->startOfDay()->utc(),
            self::Week => CarbonImmutable::now()->startOfSecond()->addWeek(),
            self::Forever => CarbonImmutable::parse('9999-12-31T00:00:00Z'),
            self::Off => null,
        };
    }
}
