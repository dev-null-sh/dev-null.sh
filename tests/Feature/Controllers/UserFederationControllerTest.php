<?php

declare(strict_types=1);

use App\Models\User;

it('lets a user be followed from Mastodon, with a key pair to sign with', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-federation.update'), ['enabled' => true])
        ->assertRedirectToRoute('user-profile.edit');

    $user->refresh();

    expect($user->federates)->toBeTrue()
        ->and($user->public_key)->toStartWith('-----BEGIN PUBLIC KEY-----');

    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page
        ->where('federates', true)
        ->where('fediverseHandle', '@'.$user->username.'@'.parse_url(config()->string('app.url'), PHP_URL_HOST)));
});

it('keeps the key pair when federation is turned off and on again', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-federation.update'), ['enabled' => true]);
    $key = $user->refresh()->public_key;

    $this->put(route('user-federation.update'), ['enabled' => false]);
    expect($user->refresh()->federates)->toBeFalse();

    $this->put(route('user-federation.update'), ['enabled' => true]);
    expect($user->refresh()->public_key)->toBe($key);
});

it('rejects missing or invalid settings', function (array $payload): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-federation.update'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('enabled');

    expect($user->refresh()->federates)->toBeFalse();
})->with([fn (): array => [], fn (): array => ['enabled' => 'yes']]);

it('requires authentication', function (): void {
    $this->putJson(route('user-federation.update'), ['enabled' => true])->assertUnauthorized();
});
