<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum ActivityScale: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * The smallest scale whose window still contains everything since the given moment.
     */
    public static function since(CarbonInterface $since, CarbonImmutable $now): self
    {
        foreach ([self::Day, self::Week, self::Month] as $scale) {
            if ($since->greaterThanOrEqualTo($scale->start($now))) {
                return $scale;
            }
        }

        return self::Year;
    }

    public function start(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Day => $now->startOfHour()->subHours(23),
            self::Week => $now->startOfDay()->subDays(6),
            self::Month => $now->startOfDay()->subDays(29),
            self::Year => $now->subWeeks(52)->startOfWeek(CarbonInterface::SUNDAY),
        };
    }

    public function isHourly(): bool
    {
        return $this === self::Day;
    }
}
