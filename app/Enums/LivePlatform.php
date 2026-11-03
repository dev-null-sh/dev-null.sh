<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Services\LivePlatformDriver;
use App\Services\LivePlatforms\Cloudflare;
use App\Services\LivePlatforms\Twitch;
use App\Services\LivePlatforms\YouTube;

enum LivePlatform: string
{
    case YouTube = 'youtube';
    case Twitch = 'twitch';
    case Cloudflare = 'cloudflare';

    /**
     * The platform a stream link points to, if any.
     */
    public static function for(string $url): ?self
    {
        return collect(self::cases())->first(fn (self $platform): bool => $platform->driver()->accepts($url));
    }

    public function driver(): LivePlatformDriver
    {
        return resolve(match ($this) {
            self::YouTube => YouTube::class,
            self::Twitch => Twitch::class,
            self::Cloudflare => Cloudflare::class,
        });
    }
}
