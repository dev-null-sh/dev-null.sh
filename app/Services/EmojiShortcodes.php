<?php

declare(strict_types=1);

namespace App\Services;

final readonly class EmojiShortcodes
{
    /**
     * @var array<string, string>
     */
    private const array MAP = [
        'heart' => '❤️',
        'like' => '👍',
        'dislike' => '👎',
        'laugh' => '😂',
        'smile' => '😄',
        'sad' => '😢',
        'angry' => '😠',
        'fire' => '🔥',
        'rocket' => '🚀',
        'clap' => '👏',
        'eyes' => '👀',
        'party' => '🎉',
    ];

    /**
     * Replaces :heart, :like, and the rest with their emoji, leaving a shortcode inside a
     * longer word (like :heartbeat) untouched. Mirrors the frontend's inline replacement.
     */
    public function replace(string $text): string
    {
        return (string) preg_replace_callback($this->pattern(), $this->replaceMatch(...), $text);
    }

    /**
     * Longest names first so a shortcode never shadows a longer one.
     */
    private function pattern(): string
    {
        $names = array_keys(self::MAP);

        usort($names, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return '/:('.implode('|', $names).')(?![A-Za-z0-9_])/';
    }

    /**
     * @param  array<int|string, string>  $matches
     */
    private function replaceMatch(array $matches): string
    {
        return self::MAP[$matches[1]];
    }
}
