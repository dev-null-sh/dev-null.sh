<?php

declare(strict_types=1);

use App\Actions\UpdateUserTimezone;
use App\Models\User;

it('may choose a timezone', function (): void {
    $user = User::factory()->create();

    $action = resolve(UpdateUserTimezone::class);

    $action->handle($user, 'America/New_York');

    expect($user->refresh()->timezone)->toBe('America/New_York');
});

it('may clear the timezone', function (): void {
    $user = User::factory()->create(['timezone' => 'America/New_York']);

    $action = resolve(UpdateUserTimezone::class);

    $action->handle($user, null);

    expect($user->refresh()->timezone)->toBeNull();
});

it('may show the local time on the profile', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserTimezone::class)->handle($user, 'America/New_York', true);

    expect($user->refresh()->show_local_time)->toBeTrue();
});

it('does not show the local time by default', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserTimezone::class)->handle($user, 'America/New_York');

    expect($user->refresh()->show_local_time)->toBeFalse();
});

it('stops showing the local time once the timezone is cleared', function (): void {
    $user = User::factory()->create(['timezone' => 'America/New_York', 'show_local_time' => true]);

    resolve(UpdateUserTimezone::class)->handle($user, null, true);

    expect($user->refresh())
        ->timezone->toBeNull()
        ->show_local_time->toBeFalse();
});
