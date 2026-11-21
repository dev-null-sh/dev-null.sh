<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;

it('starts and ends a stream for the signed user', function (): void {
    $user = User::factory()->create();
    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'PUT', route('api-user-live.update'), ['url' => ' https://twitch.tv/octocat ', 'hours' => 3])
        ->assertOk()->assertJsonPath('data.live.url', 'https://www.twitch.tv/octocat')->assertJsonPath('unread_notifications_count', 0);
    expect($user->refresh()->live_platform)->toBe(LivePlatform::Twitch)
        ->and($user->live_ends_at?->toIso8601String())->toBe(now()->addHours(3)->toIso8601String());
    signedApiRequest($key, 'DELETE', route('api-user-live.destroy'))->assertOk()
        ->assertJsonStructure(['data' => ['live']])->assertJsonPath('data.live', null);
    expect($user->refresh()->live_url)->toBeNull()->and($user->live_ends_at)->toBeNull();
});

it('rejects invalid stream data without going live', function (): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-live.update'), ['url' => 'https://example.com', 'hours' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors(['url', 'hours']);
    expect($user->refresh()->live_url)->toBeNull();
});
