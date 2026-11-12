<?php

declare(strict_types=1);

use App\Contracts\Services\HumanVerificationProvider;
use App\Http\Requests\CreateHumanVerificationRequest;
use App\Models\User;

it('shows the human check to a guest who is signing up', function (): void {
    $this->get(route('human-verification.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('human-verification/show'));
});

it('stores a passed human check and sends the guest back to GitHub', function (): void {
    $this->post(route('human-verification.store'), ['token' => 'turnstile-token'])
        ->assertRedirect(route('login'))
        ->assertSessionHas(CreateHumanVerificationRequest::SessionKey, true);
});

it('refuses a human check that did not pass', function (array $input, string $message): void {
    $this->post(route('human-verification.store'), $input)
        ->assertSessionHasErrors(['token' => $message])
        ->assertSessionMissing(CreateHumanVerificationRequest::SessionKey);
})->with([
    'no token' => [[], 'The token field is required.'],
    'an empty token' => [['token' => ''], 'The token field is required.'],
    'a token that is not text' => [['token' => ['turnstile-token']], 'The token field must be a string.'],
    'a token that is too long' => [['token' => str_repeat('a', 2049)], 'The token field must not be greater than 2048 characters.'],
]);

it('refuses a token that Cloudflare does not confirm', function (): void {
    $this->mock(HumanVerificationProvider::class)
        ->shouldReceive('isHuman')->with('used-token')->andReturnFalse();

    $this->post(route('human-verification.store'), ['token' => 'used-token'])
        ->assertSessionHasErrors(['token' => 'Cloudflare could not confirm that you are human. Try again.'])
        ->assertSessionMissing(CreateHumanVerificationRequest::SessionKey);
});

it('sends a signed-in user away from the human check', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('human-verification.show'))->assertRedirect();
    $this->post(route('human-verification.store'), ['token' => 'turnstile-token'])
        ->assertRedirect()
        ->assertSessionMissing(CreateHumanVerificationRequest::SessionKey);
});
