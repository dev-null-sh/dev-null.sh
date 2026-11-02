<?php

declare(strict_types=1);

use App\Actions\CreateUserMute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('may mute a user without telling them or touching follows', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $user->following()->attach($muted);
    $muted->following()->attach($user);

    resolve(CreateUserMute::class)->handle($user, $muted);

    expect($user->isMuting($muted))->toBeTrue()
        ->and($muted->isMuting($user))->toBeFalse()
        ->and($user->isFollowing($muted))->toBeTrue()
        ->and($muted->isFollowing($user))->toBeTrue()
        ->and($muted->notifications()->exists())->toBeFalse();
});

it('mutes in one insert without reading existing mutes', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();
    $action = resolve(CreateUserMute::class);

    $this->travelTo(now()->subDay());

    DB::flushQueryLog();
    DB::enableQueryLog();

    $action->handle($user, $muted);

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($queries->first())->toStartWith('insert into "mutes"');

    $original = $user->muting()->sole()->pivot;

    $this->travelBack();

    $action->handle($user, $muted);

    $mute = $user->muting()->sole()->pivot;

    expect($user->muting()->count())->toBe(1)
        ->and($mute->created_at->equalTo($original->created_at))->toBeTrue()
        ->and($mute->updated_at->equalTo($original->updated_at))->toBeTrue();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();

    $action = resolve(CreateUserMute::class);

    $action->handle($user, $muted);
    $action->handle($user, $muted);

    expect($user->muting()->count())->toBeOne();
});
