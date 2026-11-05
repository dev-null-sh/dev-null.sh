<?php

declare(strict_types=1);

use App\Services\PlaylistProviders\NullPlaylistProvider;

it('finds every playlist empty', function (): void {
    expect(new NullPlaylistProvider()->tracks('https://audius.co/Byte/playlist/lofi'))->toBe([]);
});
