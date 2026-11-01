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

it('shows the theme the reader picked, whatever the day', function (string $choice): void {
    Date::setTestNow('2026-06-15 12:00:00');

    $this->withUnencryptedCookie('event_theme', $choice)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-auto-event-theme', false)
        ->assertSee('data-event-theme="'.$choice.'"', false);
})->with(['halloween', 'christmas', 'newyear']);

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
