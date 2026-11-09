<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/**
 * What an embed shows, from its signed URL. Each value outside the few allowed falls back to its default, so a commit
 * has a small, closed set of cards.
 *
 * @implements Arrayable<string, bool|int|string>
 */
final readonly class EmbedOptions implements Arrayable
{
    public const array REPLIES = [1, 3, 5, 10];

    public const array DEPTHS = [1, 2, 3];

    /**
     * "All" levels, which the cap on commits per card still bounds.
     */
    public const int ALL_LEVELS = 99;

    public const array MAX_HEIGHTS = [480, 640, 800];

    public function __construct(
        public bool $thread = false,
        public int $replies = 0,
        public int $depth = 3,
        public string $theme = 'auto',
        public int $maxHeight = 640,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $replies = (int) $request->query('replies');
        $depth = $request->query('depth');
        $maxHeight = $request->query('max_height');

        return new self(
            thread: $request->query('thread') === '1',
            replies: in_array($replies, self::REPLIES, true) ? $replies : 0,
            depth: match (true) {
                $depth === 'all' => self::ALL_LEVELS,
                in_array((int) $depth, self::DEPTHS, true) && is_string($depth) && ctype_digit($depth) => (int) $depth,
                default => 3,
            },
            theme: in_array($request->query('theme'), ['light', 'dark'], true) ? (string) $request->query('theme') : 'auto',
            maxHeight: match (true) {
                $maxHeight === 'none' => 0,
                is_string($maxHeight) && in_array((int) $maxHeight, self::MAX_HEIGHTS, true) => (int) $maxHeight,
                default => 640,
            },
        );
    }

    /**
     * The query that asks for these options, with only what differs from the defaults, the way URLs are signed.
     *
     * @return array<string, int|string>
     */
    public function query(): array
    {
        return array_filter([
            'thread' => $this->thread ? 1 : null,
            'replies' => $this->replies ?: null,
            'depth' => $this->replies === 0 ? null : ($this->depth === self::ALL_LEVELS ? 'all' : $this->depth),
            'theme' => $this->theme === 'auto' ? null : $this->theme,
            'max_height' => match ($this->maxHeight) {
                640 => null,
                0 => 'none',
                default => $this->maxHeight,
            },
        ], fn (int|string|null $value): bool => $value !== null);
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'thread' => $this->thread,
            'replies' => $this->replies,
            'depth' => $this->depth,
            'theme' => $this->theme,
            'maxHeight' => $this->maxHeight,
        ];
    }
}
