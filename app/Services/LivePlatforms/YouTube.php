<?php

declare(strict_types=1);

namespace App\Services\LivePlatforms;

use App\Contracts\Services\LivePlatformDriver;
use App\Dto\LiveStream;
use App\Services\EmbedProviders\YouTube as YouTubeEmbeds;
use Uri\WhatWg\Url;

final readonly class YouTube implements LivePlatformDriver
{
    public function __construct(private YouTubeEmbeds $embeds)
    {
        //
    }

    public function accepts(string $url): bool
    {
        return $this->videoId($url) !== null;
    }

    /**
     * The link becomes its plain watch page, so a link to the embed or with a start time opens the stream itself.
     * While a video is live, its `hqdefault_live` thumbnail follows the stream rather than staying on its first frame.
     */
    public function stream(string $url): LiveStream
    {
        $videoId = $this->videoId($url);

        return new LiveStream("https://www.youtube.com/watch?v={$videoId}", "https://i.ytimg.com/vi/{$videoId}/hqdefault_live.jpg");
    }

    /**
     * Only links that play a single video can be a stream, and only those have a poster, which carries the video's id.
     */
    private function videoId(string $url): ?string
    {
        $parsed = Url::parse($url);
        $poster = $parsed instanceof Url ? $this->embeds->embed($parsed)?->poster : null;

        return preg_match('#^https://i\.ytimg\.com/vi/([A-Za-z0-9_-]{11})/#', (string) $poster, $matches) === 1 ? $matches[1] : null;
    }
}
