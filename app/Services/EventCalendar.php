<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * Writes an event as an RFC 5545 calendar. The UID stays the same and the SEQUENCE grows with every change, so adding it again updates the entry instead of duplicating it.
 */
final readonly class EventCalendar
{
    private const int MAX_LINE_OCTETS = 75;

    public function render(Event $event): string
    {
        $commit = $event->commit;
        $url = route('commit.show', [$commit->user, $commit]);
        $host = parse_url(config()->string('app.url'), PHP_URL_HOST);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//devnull//events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$event->id.'@'.(is_string($host) ? $host : 'devnull'),
            'SEQUENCE:'.$event->sequence,
            'DTSTAMP:'.$this->time(now()),
            'DTSTART:'.$this->time($event->starts_at),
            'DTEND:'.$this->time($event->ends_at),
            'SUMMARY:'.$this->text($event->title),
            'DESCRIPTION:'.$this->text($commit->body."\n\n".$url),
            'LOCATION:'.$this->text($event->location()),
            'URL:'.$url,
            'STATUS:'.($event->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'TRIGGER:-PT1H',
            'DESCRIPTION:'.$this->text($event->title),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function time(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escapes TEXT values: backslashes first, so the escapes added afterwards are not doubled.
     */
    private function text(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\;', '\,', '\n', '', '\n'],
            $value,
        );
    }

    /**
     * Folds lines longer than 75 octets: each continuation starts with a space, and characters are never split.
     */
    private function fold(string $line): string
    {
        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $character) {
            $limit = $folded === '' ? self::MAX_LINE_OCTETS : self::MAX_LINE_OCTETS - 1;

            if (mb_strlen($current.$character, '8bit') > $limit) {
                $folded .= $current."\r\n ";
                $current = '';
            }

            $current .= $character;
        }

        return $folded.$current;
    }
}
