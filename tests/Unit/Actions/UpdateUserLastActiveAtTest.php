<?php

declare(strict_types=1);

use App\Actions\UpdateUserLastActiveAt;
use App\Models\User;
use App\Services\Presence;

it('marks a user who was never active as active now', function (): void {
    $user = User::factory()->create(['last_activity_at' => null]);

    resolve(UpdateUserLastActiveAt::class)->handle($user);

    expect($user->refresh()->last_activity_at?->timestamp)->toBe(now()->timestamp);
});

it('refreshes a stale timestamp', function (): void {
    $user = User::factory()->create(['last_activity_at' => now()->subMinutes(Presence::REFRESH_AFTER_MINUTES)]);

    resolve(UpdateUserLastActiveAt::class)->handle($user);

    expect($user->refresh()->last_activity_at?->timestamp)->toBe(now()->timestamp);
});

it('leaves a fresh timestamp alone', function (): void {
    $lastActivityAt = now()->subMinutes(Presence::REFRESH_AFTER_MINUTES - 1);
    $user = User::factory()->create(['last_activity_at' => $lastActivityAt]);

    resolve(UpdateUserLastActiveAt::class)->handle($user);

    expect($user->refresh()->last_activity_at?->timestamp)->toBe($lastActivityAt->timestamp);
});

it('does not touch the updated at timestamp', function (): void {
    $user = User::factory()->create([
        'last_activity_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    resolve(UpdateUserLastActiveAt::class)->handle($user);

    expect($user->refresh()->updated_at->timestamp)->toBe(now()->subHour()->timestamp);
});
