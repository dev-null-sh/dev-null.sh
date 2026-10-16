<?php

declare(strict_types=1);

use App\Models\Event;
use App\Services\EventCalendar;

function ics(Event $event): string
{
    return resolve(EventCalendar::class)->render($event->load('commit.user'));
}

/**
 * @return list<string>
 */
function unfolded(string $ics): array
{
    return explode("\r\n", str_replace("\r\n ", '', mb_rtrim($ics, "\r\n")));
}

it('renders a calendar with one event in utc', function (): void {
    $event = Event::factory()->create([
        'title' => 'Async PHP night',
        'starts_at' => '2026-11-12 17:00:00',
        'ends_at' => '2026-11-12 19:30:00',
    ]);

    $lines = unfolded(ics($event));

    expect($lines[0])->toBe('BEGIN:VCALENDAR')
        ->and($lines)->toContain('VERSION:2.0')
        ->toContain('DTSTART:20261112T170000Z')
        ->toContain('DTEND:20261112T193000Z')
        ->toContain('SUMMARY:Async PHP night')
        ->toContain('SEQUENCE:0')
        ->toContain('STATUS:CONFIRMED')
        ->toContain('TRIGGER:-PT1H')
        ->toContain('UID:'.$event->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST))
        ->and(end($lines))->toBe('END:VCALENDAR');
});

it('uses crlf line endings', function (): void {
    $ics = ics(Event::factory()->create());

    expect(str_replace("\r\n", '', $ics))->not->toContain("\n");
});

it('escapes text so it cannot add properties', function (): void {
    $event = Event::factory()->create(['title' => "Rust; C, \"Go\" \\ done\nDTSTART:19700101T000000Z"]);

    $lines = unfolded(ics($event));

    expect($lines)->toContain('SUMMARY:Rust\; C\, "Go" \\\\ done\nDTSTART:19700101T000000Z')
        ->and(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'DTSTART:')))->toHaveCount(1);
});

it('folds long lines at 75 octets without splitting characters', function (): void {
    $event = Event::factory()->create(['title' => str_repeat('Привіт 🚀 ', 13)]);

    foreach (explode("\r\n", ics($event)) as $line) {
        expect(mb_strlen($line, '8bit'))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
});

it('marks cancelled events and carries the sequence', function (): void {
    $lines = unfolded(ics(Event::factory()->cancelled()->create(['sequence' => 3])));

    expect($lines)->toContain('STATUS:CANCELLED')->toContain('SEQUENCE:3');
});

it('puts the join link into the location', function (): void {
    $event = Event::factory()->online()->create(['join_url' => 'https://zoom.us/j/meeting']);

    expect(unfolded(ics($event)))->toContain('LOCATION:https://zoom.us/j/meeting');
});
