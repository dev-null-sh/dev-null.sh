<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;

it('lists followed people who are live without exposing their private fields', function (): void {
    $user = User::factory()->create();
    $live = User::factory()->create(['live_platform' => LivePlatform::Twitch, 'live_url' => 'https://www.twitch.tv/octocat', 'live_ends_at' => now()->addHour()]);
    $expired = User::factory()->create(['live_platform' => LivePlatform::Twitch, 'live_url' => 'https://www.twitch.tv/expired', 'live_ends_at' => now()->subMinute()]);
    $user->following()->attach([$live->id, $expired->id]);

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-live-following.index'))->assertOk()
        ->assertJsonPath('data.*.id', [$live->id])
        ->assertJsonPath('data.0.live.url', 'https://www.twitch.tv/octocat')
        ->assertJsonMissingPath('data.0.email');
});
