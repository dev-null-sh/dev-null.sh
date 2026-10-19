<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class Hihaho implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'player.hihaho.com'
            || preg_match('#^/(?:embed/)?([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?$#i', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::video('hihaho', 'https://player.hihaho.com/embed/'.mb_strtolower($matches[1]), ['autoplay' => 1]);
    }
}
