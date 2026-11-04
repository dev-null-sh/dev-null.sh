<?php

declare(strict_types=1);

namespace App\Enums;

use App\Dto\ReaderClock;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use IntlDateFormatter;

enum EventTheme: string
{
    case Halloween = 'halloween';
    case Christmas = 'christmas';
    case NewYear = 'newyear';

    private const string DEFAULT = 'none';

    private const string AUTO = 'auto';

    /**
     * The theme to show a reader: what they picked in their settings, on the day it is where they are.
     */
    public static function forRequest(Request $request): ?self
    {
        $choice = $request->cookie('event_theme');

        return self::current(is_string($choice) ? $choice : null, self::readerDate($request));
    }

    /**
     * What auto means for a reader today, whatever they picked.
     */
    public static function autoForRequest(Request $request): ?self
    {
        return self::on(self::readerDate($request));
    }

    /**
     * The year a New Year greeting wishes: the coming one until the stroke of midnight, then the current one.
     */
    public static function greetedYear(Request $request): int
    {
        $date = self::readerDate($request);

        return $date->month === 12 ? $date->year + 1 : $date->year;
    }

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
        if ($cookie === self::DEFAULT) {
            return self::DEFAULT;
        }

        return self::tryFrom((string) $cookie)->value ?? self::AUTO;
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

    /**
     * Read the local calendar with ICU so browser aliases never reach PHP's date parser.
     */
    private static function readerDate(Request $request): CarbonInterface
    {
        $now = now();
        $formatter = new IntlDateFormatter(
            'en_US',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            ReaderClock::of($request)->timeZone ?? $now->getTimezone(),
            IntlDateFormatter::GREGORIAN,
            'yyyy-MM-dd',
        );
        $date = $formatter->format($now);
        assert(is_string($date));

        return Date::parse($date, 'UTC');
    }
}
