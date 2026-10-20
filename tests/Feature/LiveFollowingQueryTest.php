<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;
use App\Queries\LiveFollowingQuery;

it('only lists followed people with active streams and respects mutes and blocks', function (): void {
    $viewer = User::factory()->create();
    $people = User::factory()->count(6)->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHours(2),
    ]);
    [$live, $expired, $muted, $blocked, $blocker, $stranger] = $people->all();
    $viewer->following()->attach([$live->id, $expired->id, $muted->id, $blocked->id, $blocker->id]);
    $expired->update(['live_ends_at' => now()->subMinute()]);
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    expect(resolve(LiveFollowingQuery::class)->get($viewer)->pluck('id')->all())->toBe([$live->id]);
});

it('returns no live people when none are followed', function (): void {
    expect(resolve(LiveFollowingQuery::class)->get(User::factory()->create()))->toBeEmpty();
});
