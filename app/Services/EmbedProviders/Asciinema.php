<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class Asciinema implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'asciinema.org') {
            return null;
        }

        if (preg_match('#^/a/(\w+)(?:/iframe)?/?$#', $url->getPath(), $matches) === 1) {
            return Embed::fixed('asciinema', EmbedKind::Code, "https://asciinema.org/a/{$matches[1]}/iframe", 420, ['autoplay' => 1]);
        }

        if (preg_match('#^/s/([A-Za-z0-9]+)/?$#', $url->getPath(), $matches) === 1) {
            return Embed::asciinemaStream($matches[1]);
        }

        return null;
    }
}
