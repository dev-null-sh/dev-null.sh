<?php

declare(strict_types=1);

use App\Enums\EventTheme;
use Illuminate\Support\Facades\Date;

it('picks the theme the calendar calls for', function (string $date, ?EventTheme $theme): void {
    expect(EventTheme::on(Date::parse($date)))->toBe($theme);
})->with([
    'before the Halloween window' => ['2026-10-28 23:59:59', null],
    'two days before Halloween' => ['2026-10-29 00:00:00', EventTheme::Halloween],
    'Halloween' => ['2026-10-31 23:59:59', EventTheme::Halloween],
    'the day after Halloween' => ['2026-11-01 12:00:00', EventTheme::Halloween],
    'after the Halloween window' => ['2026-11-02 00:00:00', null],
    'before the Christmas window' => ['2026-12-22 23:59:59', null],
    'two days before Christmas Eve' => ['2026-12-23 00:00:00', EventTheme::Christmas],
    'Christmas' => ['2026-12-25 12:00:00', EventTheme::Christmas],
    'the day after Christmas' => ['2026-12-26 23:59:59', EventTheme::Christmas],
    'between Christmas and New Year' => ['2026-12-29 23:59:59', null],
    'two days before New Year' => ['2026-12-30 00:00:00', EventTheme::NewYear],
    'New Year\'s Eve' => ['2026-12-31 12:00:00', EventTheme::NewYear],
    'New Year\'s Day' => ['2027-01-01 12:00:00', EventTheme::NewYear],
    'the day after New Year' => ['2027-01-02 23:59:59', EventTheme::NewYear],
    'after the New Year window' => ['2027-01-03 00:00:00', null],
    'an ordinary day' => ['2026-06-15 12:00:00', null],
]);

it('lets what the reader picked win over the calendar', function (?string $choice, ?EventTheme $theme): void {
    expect(EventTheme::current($choice, Date::parse('2026-10-31 12:00:00')))->toBe($theme);
})->with([
    'no choice' => [null, EventTheme::Halloween],
    'auto' => ['auto', EventTheme::Halloween],
    'an empty value' => ['', EventTheme::Halloween],
    'an unknown theme' => ['easter', EventTheme::Halloween],
    'Christmas' => ['christmas', EventTheme::Christmas],
    'New Year' => ['newyear', EventTheme::NewYear],
    'the usual colors' => ['none', null],
]);

it('shows the usual colors on an ordinary day unless the reader picked a theme', function (): void {
    $june = Date::parse('2026-06-15 12:00:00');

    expect(EventTheme::current('auto', $june))->toBeNull()
        ->and(EventTheme::current('halloween', $june))->toBe(EventTheme::Halloween);
});

it('lists what the reader picked', function (?string $cookie, string $choice): void {
    expect(EventTheme::choiceFrom($cookie))->toBe($choice);
})->with([
    'no cookie' => [null, 'auto'],
    'auto' => ['auto', 'auto'],
    'an unknown value' => ['easter', 'auto'],
    'the usual colors' => ['none', 'none'],
    'Halloween' => ['halloween', 'halloween'],
    'Christmas' => ['christmas', 'christmas'],
    'New Year' => ['newyear', 'newyear'],
]);
