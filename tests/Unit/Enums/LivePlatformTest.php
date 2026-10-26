<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Services\LivePlatforms\Twitch;
use App\Services\LivePlatforms\YouTube;

it('finds the platform a stream link points to', function (): void {
    expect(LivePlatform::for('https://www.youtube.com/live/dQw4w9WgXcQ'))->toBe(LivePlatform::YouTube)
        ->and(LivePlatform::for('https://www.twitch.tv/octocat'))->toBe(LivePlatform::Twitch)
        ->and(LivePlatform::for('https://example.com/live'))->toBeNull();
});

it('resolves each platform driver from the container', function (): void {
    expect(LivePlatform::YouTube->driver())->toBeInstanceOf(YouTube::class)
        ->and(LivePlatform::Twitch->driver())->toBeInstanceOf(Twitch::class);
});
