<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Hashtags
{
    /**
     * A handle may start with numbers, as in "3dprinting", but then needs a letter so "#123" stays an issue reference,
     * and must not be hex so "#3d3d3d" stays a color.
     */
    public const string HANDLE = '(?![0-9](?:[0-9a-fA-F]{7}|[0-9a-fA-F]{5}|[0-9a-fA-F]{2,3})_*(?![\p{L}\p{M}\p{N}_]))\p{N}*\p{L}[\p{L}\p{M}\p{N}_]*';

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
