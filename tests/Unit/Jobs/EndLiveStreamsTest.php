<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Jobs\EndLiveStreams;
use App\Models\User;

it('clears the streams that ran out', function (): void {
    $live = ['live_platform' => LivePlatform::YouTube, 'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'];
    $ended = User::factory()->create([...$live, 'live_ends_at' => now()->subMinute()]);
    $running = User::factory()->create([...$live, 'live_ends_at' => now()->addHour()]);

    dispatch_sync(new EndLiveStreams());

    expect($ended->refresh())
        ->live_platform->toBeNull()
        ->live_url->toBeNull()
        ->live_ends_at->toBeNull()
        ->and($running->refresh()->liveStream()?->url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});
