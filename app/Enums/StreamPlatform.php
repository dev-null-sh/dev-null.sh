<?php

declare(strict_types=1);

namespace App\Enums;

enum StreamPlatform: string
{
    case Youtube = 'youtube';
    case Zoom = 'zoom';
    case Meet = 'meet';
    case Twitch = 'twitch';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Youtube => 'YouTube Live',
            self::Zoom => 'Zoom',
            self::Meet => 'Google Meet',
            self::Twitch => 'Twitch',
            self::Other => 'Online',
        };
    }
}
