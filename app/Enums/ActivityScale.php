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
    case Half = 'half';
    case Year = 'year';

    /**
     * The scales shorter than a year that a profile's activity can fit into, smallest first.
     */
    public const array FITTING = [self::Day, self::Week, self::Month, self::Half];

    /**
     * The smallest of the given scales whose window still contains everything since the given moment, or else the year.
     *
     * @param  list<self>  $scales
     */
    public static function since(CarbonInterface $since, CarbonImmutable $now, array $scales = self::FITTING): self
    {
        foreach ($scales as $scale) {
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
            self::Half => $now->subWeeks(25)->startOfWeek(CarbonInterface::SUNDAY),
            self::Year => $now->subWeeks(52)->startOfWeek(CarbonInterface::SUNDAY),
        };
    }

    /**
     * The start of the window ending now, as the operations page has always counted: the last 24 hours, 7 days, or
     * 30 days. The week grids keep their whole weeks, since they need them.
     */
    public function rollingStart(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Day => $now->subDay(),
            self::Week => $now->subWeek(),
            self::Month => $now->subDays(30),
            self::Half, self::Year => $this->start($now),
        };
    }

    public function isHourly(): bool
    {
        return $this === self::Day;
    }
}
