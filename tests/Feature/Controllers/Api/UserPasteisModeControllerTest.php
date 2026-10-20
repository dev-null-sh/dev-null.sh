<?php

declare(strict_types=1);

use App\Models\User;

it('turns pastéis mode on and off, and answers as the current user endpoint does', function (bool $enabled): void {
    $user = User::factory()->create(['pasteis_mode' => ! $enabled]);
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-pasteis-mode.update'), ['enabled' => $enabled]);

    $response->assertOk()
        ->assertJsonPath('data.pasteis_mode', $enabled)
        ->assertExactJson(signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->json());

    expect($user->refresh()->pasteis_mode)->toBe($enabled);
})->with([true, false]);

it('keeps what the user gave and was given today when the mode goes off and on again', function (): void {
    $user = User::factory()->create(['pasteis_mode' => true, 'pasteis_received' => 3, 'pasteis_given_today' => 4, 'pasteis_given_on' => today()]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-pasteis-mode.update'), ['enabled' => false])->assertOk();

    signedApiRequest($keyPair, 'PUT', route('api-user-pasteis-mode.update'), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.pasteis_remaining', 6)
        ->assertJsonPath('data.pasteis_received', 3);
});

it('needs to be told on or off', function (mixed $enabled): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-pasteis-mode.update'), $enabled === null ? [] : ['enabled' => $enabled])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect($user->refresh()->pasteis_mode)->toBeFalse();
})->with([null, 'sometimes']);

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-pasteis-mode.update'), ['enabled' => true])->assertUnauthorized();
});
