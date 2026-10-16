<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class Spotify implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'open.spotify.com'
            || preg_match('#^(?:/intl-[\w-]+)?(?:/embed)?/(track|album|playlist|artist|episode|show)/([A-Za-z0-9]{22})/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::fixed('Spotify', EmbedKind::Audio, "https://open.spotify.com/embed/{$matches[1]}/{$matches[2]}", in_array($matches[1], ['track', 'episode'], true) ? 152 : 352);
    }
}
