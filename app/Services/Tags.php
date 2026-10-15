<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Tags
{
    /**
     * A "#" at the start, after whitespace or after an opening bracket or quote (so neither "C#", a URL fragment nor an
     * HTML entity), then an ASCII letter and up to 49 letters, digits, "-" or "_", ending in a letter or digit. A tag
     * that runs on past 50 characters is not shortened into a match. Starting with a letter keeps "#123" free for
     * issue references. Keep the rules in sync with resources/js/components/linkified-text.tsx.
     */
    private const string PATTERN = '/(?<![^\s(\["\'{])#([A-Za-z](?:[A-Za-z0-9_-]{0,48}[A-Za-z0-9])?)(?![A-Za-z0-9_-]*[A-Za-z0-9])/u';

    /**
     * Finds the distinct tags in the text, lowercased, in the order they first appear.
     *
     * @return list<string>
     */
    public function names(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }
}
