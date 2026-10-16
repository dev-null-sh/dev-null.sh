<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class SoundCloud implements EmbedProvider
{
    private const array RESERVED_PATHS = [
        'charts', 'discover', 'jobs', 'mobile', 'pages', 'pro', 'search', 'settings', 'stations', 'stream', 'terms-of-use',
        'upload', 'you',
    ];

    private const array PROFILE_TABS = ['albums', 'followers', 'following', 'likes', 'popular-tracks', 'reposts', 'sets', 'tracks'];

    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getAsciiHost(), ['soundcloud.com', 'www.soundcloud.com', 'm.soundcloud.com'], true)
            || preg_match('#^/([\w-]+)/(?:(sets)/)?([\w-]+)/?$#', $url->getPath(), $matches) !== 1
            || in_array($matches[1], self::RESERVED_PATHS, true)
            || ($matches[2] === '' && in_array($matches[3], self::PROFILE_TABS, true))) {
            return null;
        }

        $isSet = $matches[2] === 'sets';

        return Embed::fixed('SoundCloud', EmbedKind::Audio, 'https://w.soundcloud.com/player/', $isSet ? 450 : 166, [
            'url' => 'https://soundcloud.com'.mb_rtrim($url->getPath(), '/'),
            'auto_play' => 'true',
            'visual' => 'false',
            'show_comments' => 'false',
        ]);
    }
}
