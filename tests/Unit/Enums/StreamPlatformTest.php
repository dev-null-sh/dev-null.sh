<?php

declare(strict_types=1);

use App\Enums\StreamPlatform;

it('names every platform for people', function (): void {
    expect(array_map(fn (StreamPlatform $platform): string => $platform->label(), StreamPlatform::cases()))->toBe([
        'YouTube Live',
        'Zoom',
        'Google Meet',
        'Twitch',
        'Online',
    ]);
});
