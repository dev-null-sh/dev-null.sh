<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Container\Attributes\Scoped;
use Tempest\Highlight\Highlighter;

/**
 * Scoped, so that a page of commits shares one highlighter rather than registering its languages for each commit.
 */
#[Scoped]
final readonly class CodeSpans
{
    /**
     * A fenced code block, from a line starting with ``` or more (and maybe a language) to a line that is only as many
     * backticks or more, so a block can hold a shorter fence, or inline code between two backticks on one line. Keep in
     * sync with resources/js/lib/code.ts.
     */
    private const string PATTERN = '/(?<![^\n])(?<fence>`{3,})[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?\k<fence>`*[ \t]*(?![^\r\n])|`[^`\r\n]+`/';

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * Splits the text into prose and code, alternating: even indexes are prose (maybe empty), odd ones are code.
     *
     * @return list<string>
     */
    public function split(string $text): array
    {
        if (! str_contains($text, '`')) {
            return [$text];
        }

        // The prose and the code are found apart, since the fence the pattern captures isn't a part.
        $prose = preg_split(self::PATTERN, $text) ?: [$text];
        preg_match_all(self::PATTERN, $text, $matches);

        $parts = [];

        foreach ($prose as $index => $part) {
            $parts[] = $part;

            if (isset($matches[0][$index])) {
                $parts[] = $matches[0][$index];
            }
        }

        return $parts;
    }

    /**
     * Applies the callback to the prose only, leaving the code as written.
     *
     * @param  callable(string): string  $callback
     */
    public function mapProse(string $text, callable $callback): string
    {
        if (! str_contains($text, '`')) {
            return $callback($text);
        }

        $parts = $this->split($text);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = $callback($part);
            }
        }

        return implode('', $parts);
    }

    /**
     * Removes the code, leaving a line break in its place so that the prose on either side stays apart.
     */
    public function withoutCode(string $text): string
    {
        if (! str_contains($text, '`')) {
            return $text;
        }

        return implode("\n", array_filter($this->split($text), static fn (int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_KEY));
    }

    /**
     * Splits the text into prose, inline code and highlighted code blocks. The highlighter escapes the code, so a
     * block's HTML is safe to render as is. The line breaks around a block are dropped, since it is a block already.
     *
     * @return list<array{type: 'text', text: string}|array{type: 'code', code: string}|array{type: 'block', language: string|null, filename: string|null, html: string}>
     */
    public function highlight(string $text): array
    {
        if (! str_contains($text, '`')) {
            return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
        }

        $parts = $this->split($text);
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $segments[] = $this->isBlock($part) ? $this->block($part) : ['type' => 'code', 'code' => mb_substr($part, 1, -1)];

                continue;
            }

            if ($this->isBlock($parts[$index - 1] ?? null)) {
                if (str_starts_with($part, "\r\n")) {
                    $part = mb_substr($part, 2);
                } elseif (str_starts_with($part, "\n")) {
                    $part = mb_substr($part, 1);
                }
            }

            if ($this->isBlock($parts[$index + 1] ?? null)) {
                if (str_ends_with($part, "\r\n")) {
                    $part = mb_substr($part, 0, -2);
                } elseif (str_ends_with($part, "\n")) {
                    $part = mb_substr($part, 0, -1);
                }
            }

            if ($part !== '') {
                $segments[] = ['type' => 'text', 'text' => $part];
            }
        }

        return $segments;
    }

    private function isBlock(?string $part): bool
    {
        return $part !== null && str_starts_with($part, '```');
    }

    /**
     * @return array{type: 'block', language: string|null, filename: string|null, html: string}
     */
    private function block(string $part): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $part));
        [$language, $meta] = array_pad(preg_split('/[ \t]+/', mb_trim(mb_ltrim($lines[0], '`'), " \t"), 2) ?: [], 2, '');
        preg_match_all('/\{[^}]*\}|([A-Za-z][\w-]*)="([^"]*)"|[^ \t]+/', $meta, $tokens, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $attributes = [];

        foreach ($tokens as [, $key, $value]) {
            if ($key !== null && $value !== '') {
                $attributes[$key] ??= $value;
            }
        }

        return [
            'type' => 'block',
            'language' => $language === '' ? null : $language,
            'filename' => $attributes['title'] ?? $attributes['filename'] ?? null,
            'html' => $this->highlighter->parse(implode("\n", array_slice($lines, 1, -1)), $language === '' ? null : mb_strtolower($language)),
        ];
    }
}
