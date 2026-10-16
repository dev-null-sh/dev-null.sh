<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class Suno implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getAsciiHost(), ['suno.com', 'www.suno.com', 'app.suno.ai', 'suno.ai'], true)
            || preg_match('#^/(?:song|embed)/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?$#i', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::fixed('Suno', EmbedKind::Audio, 'https://suno.com/embed/'.mb_strtolower($matches[1]), 240);
    }
}
