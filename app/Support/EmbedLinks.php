<?php

declare(strict_types=1);

namespace App\Support;

use App\Dto\EmbedOptions;
use App\Models\Commit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * An embed has two links: the card's own, signed, and the commit's page flagged with `embed` and carrying the card's
 * signed query, which oEmbed consumers are given. Both are checked against the same signature.
 */
final class EmbedLinks
{
    private const string COMMIT_PATH = '#^/@[^/]+/commits/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$#i';

    /**
     * The card's signed URL, and the commit link that stands for it.
     *
     * @return array{card: string, link: string}
     */
    public static function for(Commit $commit, EmbedOptions $options): array
    {
        // Signed without the scheme and host, so a card keeps working behind a proxy or on another domain of the app.
        $card = url(URL::signedRoute('commit-embed.show', ['commit' => $commit->id, ...$options->query()], absolute: false));
        $page = route('commit.show', ['profile' => $commit->author->handle ?? '', 'commit' => $commit->id]);

        return ['card' => $card, 'link' => $page.'?embed&'.parse_url($card, PHP_URL_QUERY)];
    }

    /**
     * The commit a link to one of our commit pages points at, or null for any other link.
     */
    public static function commitId(string $link): ?string
    {
        $parts = parse_url($link);
        $app = parse_url(config()->string('app.url'));

        if (! is_array($parts) || ! is_array($app) || ($parts['host'] ?? null) !== ($app['host'] ?? null) || ($parts['port'] ?? null) !== ($app['port'] ?? null)) {
            return null;
        }

        return preg_match(self::COMMIT_PATH, $parts['path'] ?? '', $matches) === 1 ? mb_strtolower($matches[1]) : null;
    }

    /**
     * The card's URL a commit link stands for, when its signature holds.
     */
    public static function signedCardUrl(string $link, Commit $commit): ?string
    {
        $query = collect(explode('&', (string) parse_url($link, PHP_URL_QUERY)))
            ->reject(fn (string $part): bool => $part === 'embed' || $part === '')
            ->implode('&');

        $url = mb_rtrim(route('commit-embed.show', $commit).'?'.$query, '?');

        return URL::hasValidSignature(Request::create($url), absolute: false) ? $url : null;
    }
}
