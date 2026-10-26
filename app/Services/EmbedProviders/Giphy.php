<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class Giphy implements EmbedProvider
{
    private const array SUPPORTED_SCHEMES = ['http', 'https'];

    private const array SUPPORTED_HOSTS = ['giphy.com', 'www.giphy.com'];

    private const int PATH_SEGMENT_COUNT = 2;

    public function embed(Url $url): ?Embed
    {
        if (! $this->supportsUrl($url)) {
            return null;
        }

        $identifier = $this->getGifId($url->getPath());

        if ($identifier === null) {
            return null;
        }

        $mediaUrl = "https://media.giphy.com/media/{$identifier}";

        return Embed::gif('GIPHY', "{$mediaUrl}/giphy.gif", "{$mediaUrl}/giphy_s.gif");
    }

    private function supportsUrl(Url $url): bool
    {
        if (! in_array($url->getScheme(), self::SUPPORTED_SCHEMES, true)) {
            return false;
        }

        if (! in_array($url->getAsciiHost(), self::SUPPORTED_HOSTS, true)) {
            return false;
        }

        if (($url->getUsername() ?? '') !== '') {
            return false;
        }

        if (($url->getPassword() ?? '') !== '') {
            return false;
        }

        return $url->getPort() === null;
    }

    private function getGifId(string $path): ?string
    {
        $segments = explode('/', mb_trim($path, '/'));

        if (count($segments) !== self::PATH_SEGMENT_COUNT) {
            return null;
        }

        [$section, $slug] = $segments;
        $identifier = match (Str::lower($section)) {
            'gifs' => Str::afterLast($slug, '-'),
            'embed' => $slug,
            default => '',
        };

        return ctype_alnum($identifier) ? $identifier : null;
    }
}
