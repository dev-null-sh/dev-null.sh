<?php

declare(strict_types=1);

use App\Actions\UpdateUserStreakShields;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('keeps the setting and history consistent when two requests loaded the same earlier value', function (bool $initial): void {
    $user = User::factory()->create(['automatic_streak_shields' => $initial]);
    $firstRequest = User::query()->findOrFail($user->id);
    $secondRequest = User::query()->findOrFail($user->id);
    $action = resolve(UpdateUserStreakShields::class);

    $action->handle($firstRequest, ! $initial);
    $action->handle($secondRequest, $initial);

    expect($user->refresh()->automatic_streak_shields)->toBe($initial)
        ->and($secondRequest->automatic_streak_shields)->toBe($initial)
        ->and($secondRequest->getRawOriginal('automatic_streak_shields'))->toBe($initial)
        ->and(DB::table('streak_shield_preferences')->where('user_id', $user->id)->count())->toBe(1);

    $this->assertDatabaseHas('streak_shield_preferences', [
        'user_id' => $user->id,
        'effective_on' => today()->toDateString(),
        'enabled' => $initial,
    ]);
})->with([true, false]);

it('refreshes the caller when another request already saved the requested choice', function (): void {
    $caller = User::factory()->create(['automatic_streak_shields' => true]);
    $otherRequest = User::query()->findOrFail($caller->id);
    $action = resolve(UpdateUserStreakShields::class);

    $action->handle($otherRequest, false);
    $action->handle($caller, false);

    expect($caller->automatic_streak_shields)->toBeFalse()
        ->and($caller->getRawOriginal('automatic_streak_shields'))->toBeFalse()
        ->and($caller->isDirty('automatic_streak_shields'))->toBeFalse();
});
