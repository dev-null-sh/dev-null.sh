<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use App\Services\OpenGraphCanvas;
use App\Services\Urls;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class CreateCommitOpenGraphImage
{
    private const int AVATAR_SIZE = 80;

    private const int REPLYING_TO_HEIGHT = 40;

    private const int BODY_GAP = 32;

    private const int BODY_WIDTH = OpenGraphCanvas::CONTENT_RIGHT - OpenGraphCanvas::CONTENT_X;

    private const int MIN_TOP = 44;

    private const int MIN_BOTTOM = 36;

    /**
     * Shorter commits are drawn larger, as long as they fit.
     *
     * @var list<array{int, int}>
     */
    private const array BODY_SIZES = [[56, 72], [48, 64], [40, 56], [34, 48], [32, 44]];

    public function __construct(private Urls $urls)
    {
        //
    }

    public function handle(User $user, Commit $commit): string
    {
        $commit->loadPublicPreview();

        return Cache::remember(
            "open-graph-image:commit:{$commit->id}:{$commit->openGraphImageVersion()}",
            now()->addHour(),
            fn (): string => $this->render($user, $commit),
        );
    }

    private function render(User $user, Commit $commit): string
    {
        $canvas = new OpenGraphCanvas;
        $lane = $canvas->lane($user);
        $replyingTo = $commit->parent?->user?->username;

        $headerHeight = self::AVATAR_SIZE + ($replyingTo === null ? 0 : self::REPLYING_TO_HEIGHT) + self::BODY_GAP;
        [$size, $lineHeight, $body] = $this->fit($canvas, $this->urls->withShortenedUrls($commit->body), OpenGraphCanvas::FOOTER_RULE_Y - self::MIN_BOTTOM - self::MIN_TOP - $headerHeight);

        $top = max(self::MIN_TOP, intdiv(OpenGraphCanvas::FOOTER_RULE_Y - $headerHeight - count($body) * $lineHeight, 2));

        $canvas->rail($top + intdiv(self::AVATAR_SIZE, 2), $lane);
        $canvas->avatar($user, OpenGraphCanvas::CONTENT_X + 10, $top, self::AVATAR_SIZE, $lane);

        $textX = OpenGraphCanvas::CONTENT_X + 10 + self::AVATAR_SIZE + 10 + 28;
        $textWidth = OpenGraphCanvas::CONTENT_RIGHT - $textX;

        $canvas->text($canvas->truncate($canvas->name($user), 'Geist-SemiBold', 36, $textWidth), 'Geist-SemiBold', 36, $textX, $top + 36, OpenGraphCanvas::INK);
        $canvas->handle($user, 26, $textX, $top + 70, $textWidth, $lane);

        if ($replyingTo !== null) {
            $canvas->text($canvas->truncate("↳ replying to @{$replyingTo}", 'GeistMono-Regular', 26, self::BODY_WIDTH), 'GeistMono-Regular', 26, OpenGraphCanvas::CONTENT_X, $top + self::AVATAR_SIZE + 38, OpenGraphCanvas::INK_MUTED);
        }

        $bodyTop = $top + $headerHeight;

        foreach ($body as $index => $line) {
            $canvas->text($line, 'Geist-Regular', $size, OpenGraphCanvas::CONTENT_X, $bodyTop + $index * $lineHeight + intdiv($lineHeight + $size, 2) - intdiv($size, 5), OpenGraphCanvas::INK);
        }

        $canvas->footer();
        $this->meta($canvas, $commit);

        return $canvas->png();
    }

    /**
     * @return array{int, int, list<string>}
     */
    private function fit(OpenGraphCanvas $canvas, string $body, int $height): array
    {
        foreach (self::BODY_SIZES as [$size, $lineHeight]) {
            $lines = $canvas->wrap($body, 'Geist-Regular', $size, self::BODY_WIDTH);

            if (count($lines) * $lineHeight <= $height) {
                return [$size, $lineHeight, $lines];
            }
        }

        return [$size, $lineHeight, $canvas->clamp($lines, intdiv($height, $lineHeight), 'Geist-Regular', $size, self::BODY_WIDTH)];
    }

    private function meta(OpenGraphCanvas $canvas, Commit $commit): void
    {
        $x = OpenGraphCanvas::CONTENT_X;

        foreach ([
            ['commit ', 'GeistMono-Regular', OpenGraphCanvas::INK_MUTED],
            [mb_substr(str_replace('-', '', $commit->id), -7), 'GeistMono-Medium', OpenGraphCanvas::INK],
            [' · '.$commit->created_at->format('M j, Y'), 'GeistMono-Regular', OpenGraphCanvas::INK_MUTED],
        ] as [$text, $font, $color]) {
            $canvas->text($text, $font, 28, $x, OpenGraphCanvas::FOOTER_BASELINE, $color);
            $x += $canvas->width($text, $font, 28);
        }

        if ($commit->replies_count > 0) {
            $canvas->count($commit->replies_count, Str::plural('reply', $commit->replies_count), $x + 36);
        }
    }
}
