<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Container\Attributes\Scoped;
use Tempest\Highlight\Highlighter;

/**
 * Scoped, so that a page of commits shares one highlighter rather than registering its languages for each commit.
 *
 * @phpstan-type BodySegment array{type: 'text', text: string}|array{type: 'code', code: string}|array{type: 'block', language: string|null, html: string}|array{type: 'details', summary: string, segments: list<mixed>}
 */
#[Scoped]
final readonly class CodeSpans
{
    /**
     * A fenced code block, from a line starting with ``` or more (and maybe a language) to a line that is only ``` or
     * more, inline code between two backticks on one line, or a details block, from a line that is only <details> and
     * one that is only <summary>…</summary> to a line that is only </details>. Keep in sync with
     * resources/js/lib/code.ts.
     */
    private const string PATTERN = '/((?<![^\n])`{3,}[^`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?`{3,}[ \t]*(?![^\r\n])|`[^`\r\n]+`|(?<![^\n])<details>[ \t]*\r?\n[ \t]*<summary>[^\r\n]*<\/summary>[ \t]*\r?\n[\s\S]*?(?<![^\n])<\/details>[ \t]*(?![^\r\n]))/';

    /**
     * The parts of a details block: its summary, then everything between the summary's line and the closing line.
     */
    private const string DETAILS = '/\A<details>[ \t]*\r?\n[ \t]*<summary>([^\r\n]*)<\/summary>[ \t]*\r?\n([\s\S]*?)(?<![^\n])<\/details>[ \t]*\z/';

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * Splits the text into prose and code or details blocks, alternating: even indexes are prose (maybe empty), odd
     * ones are code or a details block.
     *
     * @return list<string>
     */
    public function split(string $text): array
    {
        return preg_split(self::PATTERN, $text, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    }

    /**
     * Applies the callback to the prose only, leaving the code as written. A details block is its own prose: its
     * summary and what it holds are the reader's to read, so they get the callback too.
     *
     * @param  callable(string): string  $callback
     */
    public function mapProse(string $text, callable $callback): string
    {
        $parts = $this->split($text);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = $callback($part);

                continue;
            }

            if ($this->isDetails($part)) {
                $parts[$index] = $this->mapDetails($part, $callback);
            }
        }

        return implode('', $parts);
    }

    /**
     * Removes the code, leaving a line break in its place so that the prose on either side stays apart. A details block
     * keeps its summary and what it holds, without the tags that make it a block.
     */
    public function withoutCode(string $text): string
    {
        $parts = [];

        foreach ($this->split($text) as $index => $part) {
            if ($index % 2 === 0) {
                $parts[] = $part;

                continue;
            }

            if ($this->isDetails($part)) {
                $details = $this->detailsParts($part);

                $parts[] = $details['summary']."\n".$this->withoutCode($details['body']);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Splits the text into prose, inline code, highlighted code blocks and details blocks. The highlighter escapes the
     * code, so a block's HTML is safe to render as is. The line breaks around a block are dropped, since it is a block
     * already. A details block holds segments of its own, the same way the text it was cut from does.
     *
     * @return list<BodySegment>
     */
    public function highlight(string $text): array
    {
        $parts = $this->split($text);
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $segments[] = match (true) {
                    $this->isBlock($part) => $this->block($part),
                    $this->isDetails($part) => $this->details($part),
                    default => ['type' => 'code', 'code' => mb_substr($part, 1, -1)],
                };

                continue;
            }

            if ($this->isStructure($parts[$index - 1] ?? null)) {
                $part = (string) preg_replace('/\A\r?\n/', '', $part);
            }

            if ($this->isStructure($parts[$index + 1] ?? null)) {
                $part = (string) preg_replace('/\r?\n\z/', '', $part);
            }

            if ($part !== '') {
                $segments[] = ['type' => 'text', 'text' => $part];
            }
        }

        return $segments;
    }

    /**
     * The summary of a details block: what a reader sees of it before opening it, or null when the part is not one.
     */
    public function summary(string $part): ?string
    {
        return $this->isDetails($part) ? $this->detailsParts($part)['summary'] : null;
    }

    private function isBlock(?string $part): bool
    {
        return $part !== null && str_starts_with($part, '```');
    }

    private function isDetails(?string $part): bool
    {
        return $part !== null && str_starts_with($part, '<details>');
    }

    /**
     * Whether a block spans lines on its own: a code block or a details block, each of which the text around it stands
     * apart from.
     */
    private function isStructure(?string $part): bool
    {
        return $this->isBlock($part) || $this->isDetails($part);
    }

    /**
     * Applies the callback to what a reader of a details block reads: its summary and the prose it holds, cut up the
     * same way as the text around it. Its tags stay as written.
     *
     * @param  callable(string): string  $callback
     */
    private function mapDetails(string $part, callable $callback): string
    {
        $details = $this->detailsParts($part);

        return '<details>'.$this->eol($part)
            .'<summary>'.$callback($details['summary']).'</summary>'.$this->eol($part)
            .$this->mapProse($details['body'], $callback)
            .'</details>';
    }

    /**
     * A details block, with its body cut up into segments of its own. The line breaks around the body are dropped, the
     * way the ones around a block are.
     *
     * @return array{type: 'details', summary: string, segments: list<BodySegment>}
     */
    private function details(string $part): array
    {
        $details = $this->detailsParts($part);
        $body = (string) preg_replace('/\A\r?\n/', '', $details['body']);

        return ['type' => 'details', 'summary' => $details['summary'], 'segments' => $this->highlight((string) preg_replace('/\r?\n\z/', '', $body))];
    }

    /**
     * The summary and the body of a details block, which the pattern has already picked out.
     *
     * @return array{summary: string, body: string}
     */
    private function detailsParts(string $part): array
    {
        preg_match(self::DETAILS, $part, $matches);

        return ['summary' => $matches[1] ?? '', 'body' => $matches[2] ?? ''];
    }

    /**
     * The line ending a block is written with, so that rewriting it doesn't turn a body's line endings into the other.
     */
    private function eol(string $part): string
    {
        return str_contains($part, "\r\n") ? "\r\n" : "\n";
    }

    /**
     * @return array{type: 'block', language: string|null, html: string}
     */
    private function block(string $part): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $part));
        $language = strtok(mb_ltrim($lines[0], '`'), " \t") ?: null;

        return [
            'type' => 'block',
            'language' => $language,
            'html' => $this->highlighter->parse(implode("\n", array_slice($lines, 1, -1)), $language === null ? null : mb_strtolower($language)),
        ];
    }
}
