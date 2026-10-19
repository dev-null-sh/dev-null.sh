<?php

declare(strict_types=1);

namespace App\Services;

final class ReactionEmoji
{
    public static function normalize(?string $flavor): string
    {
        return match ($flavor) {
            null, 'star' => '⭐', 'ship' => '🚀', 'fire' => '🔥',
            'brain' => '🧠', 'party' => '🎉', 'eyes' => '👀', default => $flavor,
        };
    }

    public static function key(?string $flavor): string
    {
        return hash_hmac('sha256', self::normalize($flavor), \Illuminate\Support\Facades\Config::string('app.key'));
    }
}
