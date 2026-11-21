<?php

declare(strict_types=1);

namespace App\Enums;

use App\Dto\ReaderClock;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use IntlCalendar;
use IntlGregorianCalendar;

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
        return self::current(self::choiceForRequest($request), self::readerNow($request));
    }

    /**
     * What the reader picked: saved on their account once they choose while signed in, or else in this browser.
     */
    public static function choiceForRequest(Request $request): string
    {
        $user = $request->user();
        $saved = $user instanceof User ? $user->event_theme ?? null : null;
        $choice = $saved ?? $request->cookie('event_theme');

        return self::choiceFrom(is_string($choice) ? $choice : null);
    }

    /**
     * Everything a reader may pick: auto, "none" for the usual colors, or a theme.
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return [self::AUTO, self::DEFAULT, ...array_column(self::cases(), 'value')];
    }

    /**
     * What auto means for a reader today, whatever they picked.
     */
    public static function autoForRequest(Request $request): ?self
    {
        return self::on(self::readerNow($request));
    }

    /**
     * The year a New Year greeting wishes: the coming one until the stroke of midnight, then the current one.
     */
    public static function greetedYear(Request $request): int
    {
        $now = self::readerNow($request);

        return $now->month === 12 ? $now->year + 1 : $now->year;
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
        return in_array($cookie, self::choices(), true) ? $cookie : self::AUTO;
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

    private static function readerNow(Request $request): CarbonInterface
    {
        $now = now();
        $timeZone = ReaderClock::of($request)->timeZone;

        if ($timeZone === null) {
            return $now;
        }

        $calendar = new IntlGregorianCalendar($timeZone);
        $calendar->setTime($now->getTimestampMs());

        return $now->utcOffset(($calendar->get(IntlCalendar::FIELD_ZONE_OFFSET) + $calendar->get(IntlCalendar::FIELD_DST_OFFSET)) / 60000);
    }
}
