<?php

declare(strict_types=1);

namespace App\Services\LivePlatforms;

use App\Contracts\Services\LivePlatformDriver;
use App\Dto\LiveStream;
use Illuminate\Support\Str;

final readonly class Cloudflare implements LivePlatformDriver
{
    /**
     * A stream on /dev/null starts from its owner's live input, never from a pasted link.
     */
    public function accepts(string $url): bool
    {
        return false;
    }

    /**
     * The link is the player itself, so the stream plays right where it is shown.
     */
    public function stream(string $url): LiveStream
    {
        return new LiveStream($url, Str::replaceEnd('/iframe', '/thumbnails/thumbnail.jpg', $url), embedUrl: $url);
    }
}
