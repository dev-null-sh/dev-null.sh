<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use App\Services\CodeSpans;
use App\Services\HighlightedCode;
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

    private const int CODE_AVATAR_SIZE = 56;

    private const int CODE_GAP = 16;

    private const int CODE_MARGIN = 28;

    private const int PROSE_SIZE = 26;

    private const int PROSE_LINE_HEIGHT = 34;

    private const int PROSE_LINES = 1;

    /**
     * Shorter code is drawn larger, as long as it fits.
     *
     * @var list<array{int, int}>
     */
    private const array CODE_SIZES = [[28, 40], [24, 34], [22, 31], [20, 28]];

    public function __construct(private Urls $urls, private CodeSpans $codeSpans, private HighlightedCode $highlightedCode)
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

        $code = $this->code($commit->body);

        if ($code !== null) {
            $this->drawCode($canvas, $user, $lane, $replyingTo, ...$code);
        } else {
            $this->drawBody($canvas, $user, $lane, $replyingTo, $commit->body);
        }

        $canvas->footer();
        $this->meta($canvas, $commit);

        return $canvas->png();
    }

    private function drawBody(OpenGraphCanvas $canvas, User $user, int $lane, ?string $replyingTo, string $body): void
    {
        $headerHeight = self::AVATAR_SIZE + ($replyingTo === null ? 0 : self::REPLYING_TO_HEIGHT) + self::BODY_GAP;
        [$size, $lineHeight, $lines] = $this->fit($canvas, $this->urls->withShortenedUrls($body), OpenGraphCanvas::FOOTER_RULE_Y - self::MIN_BOTTOM - self::MIN_TOP - $headerHeight);

        $top = max(self::MIN_TOP, intdiv(OpenGraphCanvas::FOOTER_RULE_Y - $headerHeight - count($lines) * $lineHeight, 2));

        $this->header($canvas, $user, $lane, $replyingTo, $top, self::AVATAR_SIZE);
        $this->lines($canvas, $lines, $size, $lineHeight, $top + $headerHeight);
    }

    /**
     * The code block in an editor window, under a smaller header and the first line of the prose before it.
     *
     * @param  non-empty-list<list<array{text: string, classes: list<string>}>>  $lines
     */
    private function drawCode(OpenGraphCanvas $canvas, User $user, int $lane, ?string $replyingTo, string $prose, ?string $language, array $lines): void
    {
        $headerHeight = self::CODE_AVATAR_SIZE + ($replyingTo === null ? 0 : self::REPLYING_TO_HEIGHT) + self::CODE_GAP;

        $prose = $prose === '' ? [] : $canvas->clamp($canvas->wrap($this->urls->withShortenedUrls($prose), 'Geist-Regular', self::PROSE_SIZE, self::BODY_WIDTH), self::PROSE_LINES, 'Geist-Regular', self::PROSE_SIZE, self::BODY_WIDTH);
        $proseHeight = $prose === [] ? 0 : count($prose) * self::PROSE_LINE_HEIGHT + self::CODE_GAP;

        $chrome = OpenGraphCanvas::WINDOW_TITLE_HEIGHT + OpenGraphCanvas::WINDOW_PADDING;
        [$size, $lineHeight, $lines] = $this->fitCode($canvas, $lines, OpenGraphCanvas::FOOTER_RULE_Y - 2 * self::CODE_MARGIN - $headerHeight - $proseHeight - $chrome);
        $windowHeight = $chrome + count($lines) * $lineHeight;

        $top = max(self::CODE_MARGIN, intdiv(OpenGraphCanvas::FOOTER_RULE_Y - $headerHeight - $proseHeight - $windowHeight, 2));

        $this->header($canvas, $user, $lane, $replyingTo, $top, self::CODE_AVATAR_SIZE);
        $this->lines($canvas, $prose, self::PROSE_SIZE, self::PROSE_LINE_HEIGHT, $top + $headerHeight);

        $windowTop = $top + $headerHeight + $proseHeight;

        $canvas->window(OpenGraphCanvas::CONTENT_X, $windowTop, self::BODY_WIDTH, $windowHeight, $language);
        $canvas->code($lines, $size, $lineHeight, OpenGraphCanvas::CONTENT_X + OpenGraphCanvas::WINDOW_PADDING, $windowTop + OpenGraphCanvas::WINDOW_TITLE_HEIGHT, self::BODY_WIDTH - 2 * OpenGraphCanvas::WINDOW_PADDING);
    }

    /**
     * The avatar on the rail, with the name and handle beside it sized to the avatar.
     *
     * @param  positive-int  $avatarSize
     */
    private function header(OpenGraphCanvas $canvas, User $user, int $lane, ?string $replyingTo, int $top, int $avatarSize): void
    {
        $nameSize = (int) round($avatarSize * 0.45);
        $handleSize = (int) round($avatarSize * 0.325);

        $canvas->rail($top + intdiv($avatarSize, 2), $lane);
        $canvas->avatar($user, OpenGraphCanvas::CONTENT_X + 10, $top, $avatarSize, $lane);

        $textX = OpenGraphCanvas::CONTENT_X + 10 + $avatarSize + 10 + 28;
        $textWidth = OpenGraphCanvas::CONTENT_RIGHT - $textX;

        $canvas->text($canvas->truncate($canvas->name($user), 'Geist-SemiBold', $nameSize, $textWidth), 'Geist-SemiBold', $nameSize, $textX, $top + $nameSize, OpenGraphCanvas::INK);
        $canvas->handle($user, $handleSize, $textX, $top + (int) round($avatarSize * 0.875), $textWidth, $lane);

        if ($replyingTo !== null) {
            $canvas->text($canvas->truncate("↳ replying to @{$replyingTo}", 'GeistMono-Regular', 26, self::BODY_WIDTH), 'GeistMono-Regular', 26, OpenGraphCanvas::CONTENT_X, $top + $avatarSize + 38, OpenGraphCanvas::INK_MUTED);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function lines(OpenGraphCanvas $canvas, array $lines, int $size, int $lineHeight, int $top): void
    {
        foreach ($lines as $index => $line) {
            $canvas->text($line, 'Geist-Regular', $size, OpenGraphCanvas::CONTENT_X, $top + $index * $lineHeight + intdiv($lineHeight + $size, 2) - intdiv($size, 5), OpenGraphCanvas::INK);
        }
    }

    /**
     * The prose before the first code block, and that block's language and lines, or null when the body has no block
     * or the first one is empty. Blank lines around the code are dropped, since the window has its own padding.
     *
     * @return array{string, string|null, non-empty-list<list<array{text: string, classes: list<string>}>>}|null
     */
    private function code(string $body): ?array
    {
        $parts = $this->codeSpans->split($body);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 || ! str_starts_with($part, '```')) {
                continue;
            }

            [$block] = $this->codeSpans->highlight($part);
            assert($block['type'] === 'block');

            $lines = $this->highlightedCode->lines($block['html']);

            while ($lines !== [] && $lines[0] === []) {
                array_shift($lines);
            }

            while ($lines !== [] && array_last($lines) === []) {
                array_pop($lines);
            }

            if ($lines === []) {
                return null;
            }

            return [mb_trim(implode('', array_slice($parts, 0, $index))), $block['language'], $lines];
        }

        return null;
    }

    /**
     * Shorter code is drawn larger, as long as every line fits, then as long as their number does, cutting the
     * longest. Code too long for the smallest size is cut after a muted ellipsis line.
     *
     * @param  non-empty-list<list<array{text: string, classes: list<string>}>>  $lines
     * @return array{int, int, list<list<array{text: string, classes: list<string>}>>}
     */
    private function fitCode(OpenGraphCanvas $canvas, array $lines, int $height): array
    {
        $longest = collect($lines)->max(fn (array $runs): int => mb_strlen(collect($runs)->implode('text')));
        $width = self::BODY_WIDTH - 2 * OpenGraphCanvas::WINDOW_PADDING;

        foreach ([true, false] as $isWidthFitted) {
            foreach (self::CODE_SIZES as [$size, $lineHeight]) {
                if (count($lines) * $lineHeight <= $height && (! $isWidthFitted || $longest <= $canvas->columns($size, $width))) {
                    return [$size, $lineHeight, $lines];
                }
            }
        }

        $count = max(1, intdiv($height, $lineHeight));

        return [$size, $lineHeight, [...array_slice($lines, 0, $count - 1), [['text' => '…', 'classes' => ['hl-comment']]]]];
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
