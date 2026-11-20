<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;

it('themes the page when the calendar calls for it', function (): void {
    Date::setTestNow('2026-10-30 12:00:00');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-auto-event-theme="halloween"', false)
        ->assertSee('data-event-theme="halloween"', false);
});

it('leaves the page alone on an ordinary day', function (): void {
    Date::setTestNow('2026-06-15 12:00:00');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-auto-event-theme', false)
        ->assertDontSee('data-event-theme="', false);
});

it('reads the date on the reader calendar', function (): void {
    // Still October 28 in UTC, already October 29 in Auckland.
    Date::setTestNow('2026-10-28 23:30:00 UTC');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-event-theme="', false);

    $this->withUnencryptedCookie('time_zone', 'Pacific/Auckland')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-event-theme="halloween"', false);
});

it('uses browser timezone aliases for the theme and greeting year', function (string $date, string $timeZone, string $theme, int $year): void {
    Date::setTestNow($date);

    $this->withUnencryptedCookie('time_zone', $timeZone)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('eventTheme', $theme)
            ->where('eventYear', $year));
})->with([
    'an eastern alias ahead of UTC' => ['2026-10-28 20:00:00 UTC', 'Asia/Calcutta', 'halloween', 2026],
    'a western alias during daylight saving time' => ['2026-10-29 04:30:00 UTC', 'US/Eastern', 'halloween', 2026],
    'a western alias after daylight saving time' => ['2026-11-02 00:30:00 UTC', 'US/Eastern', 'halloween', 2026],
    'a western alias still in the previous year' => ['2027-01-01 01:00:00 UTC', 'US/Eastern', 'newyear', 2027],
]);

it('shows the theme the reader picked, whatever the day', function (string $choice): void {
    Date::setTestNow('2026-06-15 12:00:00');

    $this->withUnencryptedCookie('event_theme', $choice)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-auto-event-theme', false)
        ->assertSee('data-event-theme="'.$choice.'"', false);
})->with(['halloween', 'christmas', 'newyear']);

it('reads the local event date from browser time zone aliases', function (string $timeZone, string $date): void {
    $this->travelTo(Date::parse($date));

    $this->withUnencryptedCookie('time_zone', $timeZone)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-auto-event-theme="halloween"', false)
        ->assertSee('data-event-theme="halloween"', false)
        ->assertInertia(fn ($page) => $page
            ->where('eventTheme', 'halloween')
            ->where('clock.timeZone', $timeZone));
})->with([
    'a former name browsers still report' => ['Asia/Calcutta', '2026-10-28 20:00:00 UTC'],
    'a legacy regional alias' => ['US/Eastern', '2026-11-02 02:00:00 UTC'],
]);

it('keeps the usual colors for readers who picked default', function (): void {
    Date::setTestNow('2026-12-25 12:00:00');

    $this->withUnencryptedCookie('event_theme', 'none')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-auto-event-theme="christmas"', false)
        ->assertDontSee('data-event-theme="', false);
});

it('follows the calendar for readers who picked auto', function (): void {
    Date::setTestNow('2026-12-25 12:00:00');

    $this->withUnencryptedCookie('event_theme', 'auto')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-event-theme="christmas"', false);
});

it('shares what the reader picked', function (?string $cookie, string $choice): void {
    $request = $cookie === null ? $this : $this->withUnencryptedCookie('event_theme', $cookie);

    $request->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eventThemeChoice', $choice));
})->with([
    'no cookie' => [null, 'auto'],
    'auto' => ['auto', 'auto'],
    'default' => ['none', 'none'],
    'Christmas' => ['christmas', 'christmas'],
    'an unknown value' => ['bogus', 'auto'],
]);

it('shares the theme on the page for the front end', function (string $date, ?string $theme): void {
    Date::setTestNow($date);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eventTheme', $theme));
})->with([
    'Halloween' => ['2026-10-31 12:00:00', 'halloween'],
    'Christmas' => ['2026-12-25 12:00:00', 'christmas'],
    'an ordinary day' => ['2026-06-15 12:00:00', null],
]);

it('shares the year a New Year greeting wishes', function (string $date, int $year): void {
    Date::setTestNow($date);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eventYear', $year));
})->with([
    "New Year's Eve" => ['2026-12-31 23:59:59', 2027],
    "New Year's Day" => ['2027-01-01 00:00:00', 2027],
    'the day after' => ['2027-01-02 12:00:00', 2027],
]);

it('tells the not found page about the theme', function (): void {
    config(['app.debug' => false]);
    Date::setTestNow('2026-10-31 12:00:00');

    $this->get('/does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('eventTheme', 'halloween'));
});
