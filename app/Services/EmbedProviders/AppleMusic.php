<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class AppleMusic implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'music.apple.com'
            || preg_match('#^/([a-z]{2})/(album|playlist|song)/([\w%-]+/)?([\w.-]+)/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        parse_str($url->getQuery() ?? '', $query);
        $track = is_string($query['i'] ?? null) && preg_match('/^\d+$/', $query['i']) === 1 ? $query['i'] : null;
        $isSingle = $matches[2] === 'song' || $track !== null;

        return Embed::fixed('Apple Music', EmbedKind::Audio, "https://embed.music.apple.com/{$matches[1]}/{$matches[2]}/{$matches[3]}{$matches[4]}", $isSingle ? 175 : 450, $track === null ? [] : ['i' => $track]);
    }
}
