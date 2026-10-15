<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use App\Services\OpenGraphCanvas;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final readonly class CreateEventOpenGraphImage
{
    private const int AVATAR_SIZE = 64;

    private const int TOP = 44;

    private const int TITLE_TOP = 212;

    private const int TITLE_WIDTH = OpenGraphCanvas::CONTENT_RIGHT - OpenGraphCanvas::CONTENT_X - self::TILE_WIDTH - 40;

    private const int TILE_WIDTH = 200;

    private const int TILE_HEIGHT = 224;

    private const int TILE_HEADER = 52;

    /**
     * Shorter titles are drawn larger, as long as they fit in three lines.
     *
     * @var list<array{int, int}>
     */
    private const array TITLE_SIZES = [[60, 68], [52, 60], [44, 52], [38, 46]];

    /**
     * Draws the host on top, the title on the left with a calendar tile beside it, and the time and place in the footer.
     */
    public function handle(User $host, Commit $commit): string
    {
        $event = $commit->event;
        assert($event instanceof Event);

        $canvas = new OpenGraphCanvas;

        $this->host($canvas, $host);
        $this->title($canvas, $event);
        $this->tile($canvas, $event->starts_at->copy()->setTimezone($event->timezone));
        $canvas->footer();
        $this->meta($canvas, $event);

        return $canvas->png();
    }

    private function host(OpenGraphCanvas $canvas, User $user): void
    {
        $lane = $canvas->lane($user);
        $textX = OpenGraphCanvas::CONTENT_X + 10 + self::AVATAR_SIZE + 28;

        $canvas->rail(self::TOP + intdiv(self::AVATAR_SIZE, 2), $lane);
        $canvas->avatar($user, OpenGraphCanvas::CONTENT_X + 10, self::TOP, self::AVATAR_SIZE, $lane);
        $canvas->text($canvas->truncate($canvas->name($user), 'Geist-SemiBold', 32, 520), 'Geist-SemiBold', 32, $textX, self::TOP + 30, OpenGraphCanvas::INK);
        $canvas->handle($user, 24, $textX, self::TOP + 62, 520, $lane);
    }

    /**
     * A cancelled event keeps its title, struck through.
     */
    private function title(OpenGraphCanvas $canvas, Event $event): void
    {
        $label = $event->isCancelled() ? 'event · cancelled' : 'event · '.$event->format->label();
        $canvas->text($label, 'GeistMono-Regular', 24, OpenGraphCanvas::CONTENT_X, self::TITLE_TOP - 24, OpenGraphCanvas::INK_MUTED);

        [$size, $lineHeight, $lines] = $this->fit($canvas, $event->title);

        foreach ($lines as $index => $line) {
            $baseline = self::TITLE_TOP + 16 + $index * $lineHeight + $size;
            $canvas->text($line, 'Geist-SemiBold', $size, OpenGraphCanvas::CONTENT_X, $baseline, OpenGraphCanvas::INK);

            if ($event->isCancelled()) {
                $strikeY = $baseline - intdiv($size, 3);
                $canvas->rect(OpenGraphCanvas::CONTENT_X, $strikeY, OpenGraphCanvas::CONTENT_X + $canvas->width($line, 'Geist-SemiBold', $size), $strikeY + 4, OpenGraphCanvas::INK);
            }
        }
    }

    /**
     * A tear-off calendar page: the month on a dark header, the day large, and the weekday below.
     */
    private function tile(OpenGraphCanvas $canvas, CarbonInterface $startsAt): void
    {
        $x1 = OpenGraphCanvas::CONTENT_RIGHT - self::TILE_WIDTH;
        $y1 = self::TITLE_TOP - 40;
        $x2 = OpenGraphCanvas::CONTENT_RIGHT;
        $y2 = $y1 + self::TILE_HEIGHT;

        $canvas->rect($x1, $y1, $x2, $y2, OpenGraphCanvas::INK);
        $canvas->rect($x1 + 3, $y1 + self::TILE_HEADER, $x2 - 3, $y2 - 3, OpenGraphCanvas::WHITE);

        $centered = function (string $text, string $font, int $size, int $y, int $color) use ($canvas, $x1): void {
            $canvas->text($text, $font, $size, $x1 + intdiv(self::TILE_WIDTH - $canvas->width($text, $font, $size), 2), $y, $color);
        };

        $centered(Str::lower($startsAt->format('M')), 'GeistMono-Medium', 26, $y1 + 36, OpenGraphCanvas::WHITE);
        $centered($startsAt->format('j'), 'Geist-SemiBold', 100, $y1 + self::TILE_HEADER + 112, OpenGraphCanvas::INK);
        $centered(Str::lower($startsAt->format('l')), 'GeistMono-Regular', 22, $y2 - 22, OpenGraphCanvas::INK_MUTED);
    }

    /**
     * The local time span and the venue or platform, then the going count.
     */
    private function meta(OpenGraphCanvas $canvas, Event $event): void
    {
        $startsAt = $event->starts_at->copy()->setTimezone($event->timezone);
        $endsAt = $event->ends_at->copy()->setTimezone($event->timezone);
        $place = $event->format->hasVenue() ? $event->venue : $event->platform?->label();

        $meta = $startsAt->format('H:i').'–'.$endsAt->format('H:i T').($place === null ? '' : ' · '.$place);
        $meta = $canvas->truncate($meta, 'GeistMono-Regular', 26, 560);

        $canvas->text($meta, 'GeistMono-Regular', 26, OpenGraphCanvas::CONTENT_X, OpenGraphCanvas::FOOTER_BASELINE, OpenGraphCanvas::INK);

        $going = $event->attendeesCount();

        if ($going > 0) {
            $canvas->count($going, 'going', OpenGraphCanvas::CONTENT_X + $canvas->width($meta, 'GeistMono-Regular', 26) + 36);
        }
    }

    /**
     * @return array{int, int, list<string>}
     */
    private function fit(OpenGraphCanvas $canvas, string $title): array
    {
        foreach (self::TITLE_SIZES as [$size, $lineHeight]) {
            $lines = $canvas->wrap($title, 'Geist-SemiBold', $size, self::TITLE_WIDTH);

            if (count($lines) <= 3) {
                return [$size, $lineHeight, $lines];
            }
        }

        return [$size, $lineHeight, $canvas->clamp($lines, 3, 'Geist-SemiBold', $size, self::TITLE_WIDTH)];
    }
}
