<?php

declare(strict_types=1);

use App\Actions\UpdateUserLane;
use App\Models\User;

it('may choose a lane', function (int $lane): void {
    $user = User::factory()->create();

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, $lane, null);

    expect($user->refresh()->preferred_lane)->toBe($lane);
})->with([0, 1, 2, 3, 4, 5]);

it('may go back to the automatic lane', function (): void {
    $user = User::factory()->create(['preferred_lane' => 4]);

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, null, null);

    expect($user->refresh()->preferred_lane)->toBeNull();
});

it('may choose a custom color over the palette', function (): void {
    $user = User::factory()->create(['preferred_lane' => 4]);

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, null, '#1a2b3c');

    expect($user->refresh()->lane_color)->toBe('#1a2b3c')
        ->and($user->preferred_lane)->toBeNull();
});

it('drops the custom color when choosing from the palette', function (): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $action = resolve(UpdateUserLane::class);

    $action->handle($user, 2, null);

    expect($user->refresh()->lane_color)->toBeNull()
        ->and($user->preferred_lane)->toBe(2);
});
