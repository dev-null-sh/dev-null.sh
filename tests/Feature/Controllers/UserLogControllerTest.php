<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\User;

it('remembers the log the user is shown', function (Log $log): void {
    $user = User::factory()->create(['log' => $log === Log::Following ? Log::Trending : Log::Following]);

    $this->actingAs($user)
        ->putJson(route('user-log.update'), ['log' => $log->value])
        ->assertOk()
        ->assertExactJson(['log' => $log->value]);

    expect($user->refresh()->log)->toBe($log);
})->with(Log::cases());

it('does not update the user when the log is unchanged', function (): void {
    $user = User::factory()->create(['log' => Log::Trending]);
    $updatedAt = $user->updated_at;

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->putJson(route('user-log.update'), ['log' => Log::Trending->value])
        ->assertOk();

    expect($user->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('refuses an unknown log', function (mixed $log): void {
    $user = User::factory()->create(['log' => Log::Trending]);

    $this->actingAs($user)
        ->putJson(route('user-log.update'), ['log' => $log])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('log');

    expect($user->refresh()->log)->toBe(Log::Trending);
})->with(['unknown', null]);

it('requires sign in to remember a log', function (): void {
    $this->putJson(route('user-log.update'), ['log' => Log::Latest->value])
        ->assertUnauthorized();
});
