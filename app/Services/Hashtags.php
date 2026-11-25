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
        $prose = $this->codeSpans->withoutCode((string) preg_replace('/^> ?/m', '', $text));
        $prose = $this->urls->withoutUrls($prose);
        $prose = preg_replace('/(?<![\p{L}\p{M}\p{N}_#])color\(#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\)/iu', ' ', $prose) ?? '';

        preg_match_all('/(?<![\p{L}\p{M}\p{N}_&\/#>-])#(\p{L}[\p{L}\p{M}\p{N}_]*)/u', $prose, $matches);

        return array_values(array_unique(array_map(mb_strtolower(...), $matches[1])));
    }
}
