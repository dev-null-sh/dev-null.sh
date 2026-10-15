<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Spoilers
{
    /**
     * Text between "||" and "||" that neither starts nor ends with whitespace, so "make test || make fix || exit" is
     * not a spoiler. Keep in sync with resources/js/lib/spoilers.ts.
     */
    private const string PATTERN = '/\|\|(?=\S)(.+?)(?<=\S)\|\|/su';

    public function __construct(private CodeSpans $codeSpans)
    {
        //
    }

    /**
     * Splits the text into prose, spoilers, inline code and highlighted code blocks. Code is never a spoiler.
     *
     * @return list<array{type: 'text', text: string}|array{type: 'spoiler', text: string}|array{type: 'code', code: string}|array{type: 'block', language: string|null, html: string}>
     */
    public function segments(string $text): array
    {
        $segments = [];

        foreach ($this->codeSpans->highlight($text) as $segment) {
            if ($segment['type'] !== 'text') {
                $segments[] = $segment;

                continue;
            }

            foreach (preg_split(self::PATTERN, $segment['text'], flags: PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $part) {
                if ($part !== '') {
                    $segments[] = ['type' => $index % 2 === 1 ? 'spoiler' : 'text', 'text' => $part];
                }
            }
        }

        return $segments;
    }

    /**
     * Replaces each spoiler outside code with "[spoiler]", for wherever the text can't be revealed on demand.
     */
    public function mask(string $text): string
    {
        return $this->codeSpans->mapProse($text, static fn (string $prose): string => (string) preg_replace(self::PATTERN, '[spoiler]', $prose));
    }
}
