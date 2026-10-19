<?php

declare(strict_types=1);

use App\Actions\CreatePendingFollows;
use App\Models\PendingFollow;
use App\Models\User;

it('remembers the github accounts the user follows that are not on devnull', function (): void {
    $user = User::factory()->create();
    $registered = User::factory()->create();

    resolve(CreatePendingFollows::class)->handle($user, ['111', $registered->github_id, '222']);
    resolve(CreatePendingFollows::class)->handle($user, ['111']);

    expect($user->pendingFollows()->orderBy('github_id')->pluck('github_id')->all())->toBe(['111', '222']);
});

it('remembers nothing when everyone is on devnull', function (): void {
    $user = User::factory()->create();

    resolve(CreatePendingFollows::class)->handle($user, [User::factory()->create()->github_id]);
    resolve(CreatePendingFollows::class)->handle($user, []);

    expect(PendingFollow::query()->count())->toBe(0);
});
