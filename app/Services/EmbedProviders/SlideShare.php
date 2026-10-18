<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

/**
 * SlideShare player URLs and public slideshow pages with a numeric id. Their 510×420 embed scales to the
 * timeline width via the same aspect ratio.
 */
final readonly class SlideShare implements EmbedProvider
{
    /** Official SlideShare embed frame is 510×420. */
    private const string ASPECT_RATIO = '17 / 14';

    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getAsciiHost(), ['slideshare.net', 'www.slideshare.net'], true)) {
            return null;
        }

        $path = $url->getPath();

        if (preg_match('#^/slideshow/embed_code/key/([A-Za-z0-9_-]+)/?$#', $path, $matches) === 1) {
            return Embed::video(
                'SlideShare',
                "https://www.slideshare.net/slideshow/embed_code/key/{$matches[1]}",
                aspectRatio: self::ASPECT_RATIO,
            );
        }

        if (preg_match('#^/slideshow/embed_code/(\d+)/?$#', $path, $matches) === 1) {
            return Embed::video(
                'SlideShare',
                "https://www.slideshare.net/slideshow/embed_code/{$matches[1]}",
                aspectRatio: self::ASPECT_RATIO,
            );
        }

        // Public deck pages: /slideshow/{slug}/{id} → embed_code/{id} (SlideShare redirects to /key/…).
        if (preg_match('#^/slideshow/([^/]+)/(\d+)/?$#', $path, $matches) === 1) {
            return Embed::video(
                'SlideShare',
                "https://www.slideshare.net/slideshow/embed_code/{$matches[2]}",
                aspectRatio: self::ASPECT_RATIO,
            );
        }

        return null;
    }
}
