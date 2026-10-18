<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

/**
 * Speaker Deck player URLs embed directly. Public deck pages need their oEmbed id, which is a later step.
 */
final readonly class SpeakerDeck implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getAsciiHost(), ['speakerdeck.com', 'www.speakerdeck.com'], true)) {
            return null;
        }

        if (preg_match('#^/player/([A-Za-z0-9_-]+)/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::video('Speaker Deck', "https://speakerdeck.com/player/{$matches[1]}");
    }
}
