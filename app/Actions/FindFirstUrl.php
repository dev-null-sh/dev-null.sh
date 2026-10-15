<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class FindFirstUrl
{
    private const int MAX_LENGTH = 2048;

    /**
     * Top-level domains that a bare domain like "nativephp.com" may end with. It is an allowlist because most file
     * extensions are also real TLDs. Leave out any that read like code or files (sh, py, rs, md, pl, cc, in, mk, ml,
     * so, tf, ps, am, cl, gd, cr, do, zip, mov, ...) or like a property in code (user.id, user.name, app.run, ...).
     * Keep the list in sync with resources/js/components/linkified-text.tsx.
     *
     * @var list<string>
     */
    private const array TOP_LEVEL_DOMAINS = [
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'info', 'biz', 'io', 'dev', 'app', 'co', 'ai', 'me', 'xyz', 'tech',
        'gg', 'tv', 'fm', 'ly', 'to', 'is', 'blog', 'cloud', 'site', 'online', 'design', 'studio', 'tools', 'software',
        'codes', 'wiki', 'news', 'website', 'club', 'ninja', 'rocks', 'academy', 'guide', 'social', 'digital', 'agency',
        'fyi', 'lol', 'live',
        'uk', 'de', 'fr', 'nl', 'es', 'it', 'pt', 'br', 'ca', 'au', 'jp', 'ch', 'se', 'no', 'dk', 'fi', 'be', 'at', 'ie',
        'nz', 'us', 'eu', 'cz', 'sk', 'hu', 'ro', 'bg', 'gr', 'hr', 'si', 'ee', 'lv', 'lt', 'ru', 'ua', 'tr', 'il', 'za',
        'mx', 'ar', 'kr', 'cn', 'tw', 'hk', 'sg', 'vn', 'th',
    ];

    /**
     * Finds the first http(s) URL or bare domain in the text, whichever comes first, and returns it as a URL. A bare
     * domain gets an https scheme. Keep the rules in sync with resources/js/components/linkified-text.tsx.
     */
    public function handle(string $text): ?string
    {
        preg_match_all($this->pattern(), $text, $matches);

        foreach ($matches[0] as $candidate) {
            $match = $this->withoutTrailingPunctuation($candidate);
            $url = preg_match('/^https?:\/\//i', $match) === 1 ? $match : 'https://'.$match;

            if (! str_ends_with($url, '://') && mb_strlen($url) <= self::MAX_LENGTH) {
                return $url;
            }
        }

        return null;
    }

    /**
     * A URL with a scheme, or a bare domain: ASCII labels ending in an allowed TLD, optionally followed by a path,
     * query or fragment. A bare domain can't follow a letter, digit, "_", ".", "/", "@" or "-" (so no email addresses,
     * handles or the middle of a URL). Its TLD must be all lowercase or all uppercase (so not "bug.It"), and must end
     * the word without a "(" or another label after it (so neither "nativephp.community", "array.at(-1)" nor
     * "socket.io.js"). There is no case-insensitive flag, so that the TLD's case can be checked.
     */
    private function pattern(): string
    {
        $topLevelDomains = implode('|', [...self::TOP_LEVEL_DOMAINS, ...array_map(mb_strtoupper(...), self::TOP_LEVEL_DOMAINS)]);

        return '/\b[Hh][Tt][Tt][Pp][Ss]?:\/\/[^\s<>]+'
            .'|(?<![\p{L}\p{N}_.\/@-])(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+(?:'.$topLevelDomains.')'
            .'(?![\p{L}\p{N}_(-]|\.[A-Za-z0-9])(?:[\/?#][^\s<>]*)?/u';
    }

    /**
     * Strips punctuation that ends a sentence rather than the URL, keeping closing brackets that pair with one inside it.
     */
    private function withoutTrailingPunctuation(string $url): string
    {
        do {
            $previous = $url;
            $url = mb_rtrim($url, '.,;:!?\'"');

            foreach (['(' => ')', '[' => ']'] as $open => $close) {
                if (str_ends_with($url, $close) && mb_substr_count($url, $open) < mb_substr_count($url, $close)) {
                    $url = mb_substr($url, 0, -1);
                }
            }
        } while ($url !== $previous);

        return $url;
    }
}
