<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('defaults to off and can be enabled or disabled without resetting awards or allowance', function (): void {
    $user = User::factory()->create(['pasteis_received' => 4]);
    $commit = Commit::factory()->create();

    expect($user->refresh()->pasteis_mode)->toBeFalse();

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-pasteis-mode.update'), ['enabled' => true])
        ->assertRedirectToRoute('user-profile.edit');
    $this->postJson(route('commit-pastel.store', $commit))->assertOk();

    $this->put(route('user-pasteis-mode.update'), ['enabled' => false])->assertRedirect();
    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page->where('auth.pasteis', null));
    $this->put(route('user-pasteis-mode.update'), ['enabled' => true])->assertRedirect();
    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page->where('auth.pasteis.remaining', 9));

    expect($user->refresh()->pasteis_received)->toBe(4)
        ->and($user->pasteis_mode)->toBeTrue();
});

it('rejects missing or invalid settings without changing the mode', function (array $payload): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-pasteis-mode.update'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('enabled');

    expect($user->refresh()->pasteis_mode)->toBeFalse();
})->with([fn (): array => [], fn (): array => ['enabled' => 'yes'], fn (): array => ['enabled' => null]]);

it('requires authentication', function (): void {
    $this->putJson(route('user-pasteis-mode.update'), ['enabled' => true])->assertUnauthorized();
});
