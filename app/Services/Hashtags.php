<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Hashtags
{
    public const string HANDLE = '\p{L}[\p{L}\p{M}\p{N}_]*';

    public function __construct(private CodeSpans $codeSpans, private Urls $urls)
    {
        //
    }

    /**
     * The hashtag a guild display name stands for: its letters, marks, numbers and underscores, lowercased.
     */
    public static function handleFor(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '', $name));
    }

    /** @return list<string> */
    public function handles(string $text): array
    {
        $urls = collect($this->urls->all($text))->flatMap(fn (string $url): array => [$url, (string) preg_replace('/^https?:\/\//i', '', $url)])->all();
        $prose = str_replace($urls, '', $this->codeSpans->withoutCode($text));
        $prose = preg_replace('/(?<![\p{L}\p{M}\p{N}_#])color\(#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\)/iu', ' ', $prose) ?? '';
        preg_match_all('/(?<![\p{L}\p{M}\p{N}_&\/#>-])#('.self::HANDLE.')/u', $prose, $matches);

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }
}
