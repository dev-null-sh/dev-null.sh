<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

/**
 * Published Google Slides (/pub or /embed) can play in place. Edit links cannot.
 */
final readonly class GoogleSlides implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'docs.google.com') {
            return null;
        }

        if (preg_match('#^/presentation/d/(?:e/)?([A-Za-z0-9_-]+)/(embed|pub)/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        $prefix = str_contains($url->getPath(), '/d/e/') ? 'e/' : '';

        return Embed::video(
            'Google Slides',
            "https://docs.google.com/presentation/d/{$prefix}{$matches[1]}/embed",
            ['start' => 'false', 'loop' => 'false'],
        );
    }
}
