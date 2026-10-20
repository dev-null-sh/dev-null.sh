<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

final readonly class EmojiShortcodes
{
    /**
     * @var array<string, string>
     */
    private array $map;

    private string $pattern;

    public function __construct(private CodeSpans $codeSpans)
    {
        $this->map = $this->load();
        $this->pattern = $this->pattern();
    }

    /**
     * Replaces :heart:, :like:, and the rest with their emoji, leaving a shortcode without its
     * closing colon (like :heart) or in code untouched. Mirrors the frontend's inline replacement.
     */
    public function replace(string $text): string
    {
        if (! str_contains($text, ':')) {
            return $text;
        }

        return $this->codeSpans->mapProse(
            $text,
            fn (string $prose): string => (string) preg_replace_callback($this->pattern, $this->replaceMatch(...), $prose),
        );
    }

    /**
     * Every emoji a shortcode stands for, once each.
     *
     * @return list<string>
     */
    public function emoji(): array
    {
        return array_values(array_unique($this->map));
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        /** @var array<string, string> $map */
        $map = File::json(resource_path('js/lib/emoji-shortcodes.json'));

        return $map;
    }

    /**
     * Longest names first so a shortcode never shadows a longer one.
     */
    private function pattern(): string
    {
        $names = array_keys($this->map);

        usort($names, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        $names = array_map(static fn (string $name): string => preg_quote($name, '/'), $names);

        return '/:('.implode('|', $names).'):/';
    }

    /**
     * @param  array<int|string, string>  $matches
     */
    private function replaceMatch(array $matches): string
    {
        return $this->map[$matches[1]];
    }
}
