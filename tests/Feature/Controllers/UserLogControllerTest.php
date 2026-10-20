<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\User;

it('requires authentication to remember a log', function (): void {
    $this->putJson(route('user-log.update'), ['log' => 'latest'])->assertUnauthorized();
});

it('remembers a viewed log only for the authenticated user', function (Log $log): void {
    $user = User::factory()->create(['log' => Log::Following]);
    $other = User::factory()->create(['log' => Log::Following]);

    $this->actingAs($user)->putJson(route('user-log.update'), ['log' => $log->value])->assertNoContent();

    expect($user->refresh()->log)->toBe($log)
        ->and($other->refresh()->log)->toBe(Log::Following);
})->with(Log::cases());

it('rejects invalid log preferences', function (mixed $log): void {
    $user = User::factory()->create(['log' => Log::Following]);

    $this->actingAs($user)->putJson(route('user-log.update'), ['log' => $log])->assertUnprocessable();

    expect($user->refresh()->log)->toBe(Log::Following);
})->with([null, '', 'unknown', 12, [['latest']]]);

it('does not write an unchanged preference', function (): void {
    $user = User::factory()->create(['log' => Log::Latest]);
    $updatedAt = $user->updated_at;
    $this->travel(1)->minute();

    $this->actingAs($user)->putJson(route('user-log.update'), ['log' => 'latest'])->assertNoContent();

    expect($user->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
