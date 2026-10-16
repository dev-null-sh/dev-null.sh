<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class Loom implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getAsciiHost(), ['loom.com', 'www.loom.com'], true)
            || preg_match('#^/(?:share|embed)/([0-9a-f]{32})/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::video('Loom', "https://www.loom.com/embed/{$matches[1]}", ['autoplay' => 1]);
    }
}
