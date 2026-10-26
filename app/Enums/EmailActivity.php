<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who an email reaches by when they were last seen: everyone, the people seen recently, or the ones who drifted away.
 */
enum EmailActivity: string
{
    case Anyone = 'anyone';
    case ActiveWeek = 'active-7d';
    case ActiveMonth = 'active-30d';
    case ActiveQuarter = 'active-90d';
    case InactiveMonth = 'inactive-30d';
    case InactiveQuarter = 'inactive-90d';

    public function days(): ?int
    {
        return match ($this) {
            self::Anyone => null,
            self::ActiveWeek => 7,
            self::ActiveMonth, self::InactiveMonth => 30,
            self::ActiveQuarter, self::InactiveQuarter => 90,
        };
    }

    public function isInactive(): bool
    {
        return in_array($this, [self::InactiveMonth, self::InactiveQuarter], true);
    }
}
