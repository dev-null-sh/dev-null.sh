<?php

declare(strict_types=1);

use App\Models\User;

it('turns Remotes on and off, and answers as the current user endpoint does', function (bool $enabled): void {
    $user = User::factory()->create(['remotes_enabled' => ! $enabled]);
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), ['enabled' => $enabled]);

    $response->assertOk()
        ->assertExactJson(signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->json());

    expect($user->refresh()->remotes_enabled)->toBe($enabled);
})->with([true, false]);

it('says nothing about Remotes while they are off, as the web hides them', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.remotes', null);

    signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.remotes.from', 'following')
        ->assertJsonPath('data.remotes.pull_receipts', true);
});

it('keeps the conversations for when Remotes come back on', function (): void {
    $user = User::factory()->create();
    $partner = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    $user->following()->attach($partner);
    sendChatMessage($partner, $user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), ['enabled' => false])->assertOk();

    signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.remotes.unread_count', 1);
});

it('needs to be told on or off', function (mixed $enabled): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remotes.update'), $enabled === null ? [] : ['enabled' => $enabled])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect($user->refresh()->remotes_enabled)->toBeTrue();
})->with([null, 'sometimes']);

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-remotes.update'), ['enabled' => false])->assertUnauthorized();
});
