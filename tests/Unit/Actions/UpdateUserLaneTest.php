<?php

declare(strict_types=1);

use App\Actions\UpdateUserLane;
use App\Models\User;

it('may choose a lane', function (int $lane): void {
    $user = User::factory()->create();

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, $lane);

    expect($user->refresh()->preferred_lane)->toBe($lane);
})->with([0, 1, 2, 3, 4, 5]);

it('may go back to the automatic lane', function (): void {
    $user = User::factory()->create(['preferred_lane' => 4]);

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, null);

    expect($user->refresh()->preferred_lane)->toBeNull();
});
