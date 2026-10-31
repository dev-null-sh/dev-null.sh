<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum EventTheme: string
{
    case Halloween = 'halloween';
    case Christmas = 'christmas';
    case NewYear = 'newyear';

    private const string DEFAULT = 'none';

    private const string AUTO = 'auto';

    /**
     * The theme to show: the one the reader picked ("none" picks no theme), or the one the calendar calls for on that
     * date when they left it on auto.
     */
    public static function current(?string $choice, CarbonInterface $date): ?self
    {
        if ($choice === self::DEFAULT) {
            return null;
        }

        return self::tryFrom((string) $choice) ?? self::on($date);
    }

    /**
     * What the reader picked, as the settings page lists it: a theme, "none" for the usual colors, or "auto".
     */
    public static function choiceFrom(?string $cookie): string
    {
        if ($cookie === self::DEFAULT || self::tryFrom((string) $cookie) instanceof self) {
            return $cookie;
        }

        return self::AUTO;
    }

    /**
     * Each theme runs from two days before the day to the day after it.
     */
    public static function on(CarbonInterface $date): ?self
    {
        $monthDay = (int) $date->format('md');

        return match (true) {
            $monthDay >= 1029 && $monthDay <= 1101 => self::Halloween,
            $monthDay >= 1223 && $monthDay <= 1226 => self::Christmas,
            $monthDay >= 1230 || $monthDay <= 102 => self::NewYear,
            default => null,
        };
    }
}
