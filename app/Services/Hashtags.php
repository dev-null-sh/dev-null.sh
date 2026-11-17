<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Hashtags
{
    public function __construct(private CodeSpans $codeSpans, private Urls $urls)
    {
        //
    }

    /** @return list<string> */
    public function handles(string $text): array
    {
        $urls = collect($this->urls->all($text))->flatMap(fn (string $url): array => [$url, (string) preg_replace('/^https?:\/\//i', '', $url)])->all();
        $prose = str_replace($urls, '', $this->codeSpans->withoutCode($text));
        preg_match_all('/(?<![\p{L}\p{M}\p{N}_&\/#>-])#(\p{L}[\p{L}\p{M}\p{N}_]*)/u', $prose, $matches);

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }
}
