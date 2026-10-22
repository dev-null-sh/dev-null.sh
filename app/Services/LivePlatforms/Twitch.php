<?php

declare(strict_types=1);

namespace App\Services\LivePlatforms;

use App\Contracts\Services\LivePlatformDriver;
use App\Dto\LiveStream;
use App\Services\EmbedProviders\Twitch as TwitchEmbeds;
use Uri\WhatWg\Url;

final readonly class Twitch implements LivePlatformDriver
{
    public function __construct(private TwitchEmbeds $embeds)
    {
        //
    }

    public function accepts(string $url): bool
    {
        return $this->channel($url) !== null;
    }

    /**
     * The stream link points to the channel page on Twitch.
     * While a channel is live, its preview thumbnail is updated periodically.
     */
    public function stream(string $url): LiveStream
    {
        $channel = $this->channel($url);

        return new LiveStream("https://www.twitch.tv/{$channel}", "https://static-cdn.jtvnw.net/previews-ttv/live_user_{$channel}-1280x720.jpg");
    }

    /**
     * Only links pointing to a user's Twitch channel can be a stream, which embed as a channel player.
     */
    private function channel(string $url): ?string
    {
        $parsed = Url::parse($url);
        $src = $parsed instanceof Url ? $this->embeds->embed($parsed)?->src : null;

        return preg_match('#[?&]channel=([A-Za-z0-9_]{3,25})(?:&|$)#', (string) $src, $matches) === 1 ? mb_strtolower($matches[1]) : null;
    }
}
