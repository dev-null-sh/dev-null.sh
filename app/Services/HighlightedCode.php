<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads the HTML that the highlighter makes of a code block back into lines of text, so that it can be drawn somewhere
 * HTML can't go, like a link preview image.
 */
final readonly class HighlightedCode
{
    private const int TAB_WIDTH = 4;

    /**
     * Splits the HTML into lines of runs of text, each with the classes of every span it sits in, outermost first.
     * Spans nest (an hl-injection holds an hl-type), may run across lines (a block comment), and may have no class.
     *
     * @return list<list<array{text: string, classes: list<string>}>>
     */
    public function lines(string $html): array
    {
        $parts = preg_split('/(<span(?: class="[^"]*")?>|<\/span>)/', $html, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        /** @var list<list<string>> $stack */
        $stack = [];
        $lines = [[]];
        $column = 0;

        foreach ($parts as $part) {
            if ($part === '</span>') {
                array_pop($stack);

                continue;
            }

            if (str_starts_with($part, '<span')) {
                preg_match('/class="([^"]*)"/', $part, $matches);

                $stack[] = preg_split('/\s+/', $matches[1] ?? '', flags: PREG_SPLIT_NO_EMPTY) ?: [];

                continue;
            }

            foreach (explode("\n", html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $index => $text) {
                if ($index > 0) {
                    $lines[] = [];
                    $column = 0;
                }

                if ($text === '') {
                    continue;
                }

                $text = $this->expandTabs($text, $column);
                $column += mb_strlen($text);

                $lines[array_key_last($lines)][] = ['text' => $text, 'classes' => array_merge(...$stack)];
            }
        }

        return $lines;
    }

    /**
     * Replaces each tab with the spaces up to the next tab stop, counting from where the text starts on its line.
     */
    private function expandTabs(string $text, int $column): string
    {
        if (! str_contains($text, "\t")) {
            return $text;
        }

        $expanded = '';

        foreach (mb_str_split($text) as $character) {
            $expanded .= $character === "\t"
                ? str_repeat(' ', self::TAB_WIDTH - ($column + mb_strlen($expanded)) % self::TAB_WIDTH)
                : $character;
        }

        return $expanded;
    }
}
