<?php

declare(strict_types=1);

use App\Actions\RegenerateFeedToken;
use App\Models\User;

it('gives the user a feed token', function (): void {
    $user = User::factory()->create();

    $token = resolve(RegenerateFeedToken::class)->handle($user);

    expect($token)->toHaveLength(48)
        ->and($user->refresh()->feed_token)->toBe($token);
});

it('replaces the previous feed token', function (): void {
    $user = User::factory()->create(['feed_token' => 'old-feed-token']);

    $token = resolve(RegenerateFeedToken::class)->handle($user);

    expect($token)->not->toBe('old-feed-token')
        ->and($user->refresh()->feed_token)->toBe($token);
});

it('only changes the given user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['feed_token' => 'other-feed-token']);

    resolve(RegenerateFeedToken::class)->handle($user);

    expect($other->refresh()->feed_token)->toBe('other-feed-token');
});

it('does not touch the profile timestamp', function (): void {
    $user = User::factory()->create();
    $updatedAt = $user->updated_at->toIso8601String();

    $this->travel(1)->hour();

    resolve(RegenerateFeedToken::class)->handle($user);

    expect($user->refresh()->updated_at->toIso8601String())->toBe($updatedAt);
});
