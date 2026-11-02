<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Models\User;

it('saves who can push to the user and whether they share pulls, and answers as the current user endpoint does', function (): void {
    config(['remotes.everyone' => true]);

    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-remote-preferences.update'), [
        'from' => 'everyone',
        'pull_receipts' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.remotes.from', 'everyone')
        ->assertJsonPath('data.remotes.pull_receipts', false)
        ->assertJsonPath('data.remotes.everyone', true)
        ->assertExactJson(signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->json());

    $user->refresh();

    expect($user->remotes_from)->toBe(RemotesFrom::Everyone)
        ->and($user->pull_receipts)->toBeFalse();
});

it('only offers everyone while the site does', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remote-preferences.update'), ['from' => 'everyone', 'pull_receipts' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from');

    signedApiRequest($keyPair, 'PUT', route('api-user-remote-preferences.update'), ['from' => 'following', 'pull_receipts' => false])
        ->assertOk()
        ->assertJsonPath('data.remotes.from', 'following')
        ->assertJsonPath('data.remotes.everyone', false);

    expect($user->refresh()->remotes_from)->toBe(RemotesFrom::Following);
});

it('needs both settings', function (array $data, string $field): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remote-preferences.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no from' => [['pull_receipts' => true], 'from'],
    'another from' => [['from' => 'nobody', 'pull_receipts' => true], 'from'],
    'no pull receipts' => [['from' => 'following'], 'pull_receipts'],
]);

it('is not there while Remotes are off, as on the web', function (): void {
    $user = User::factory()->create(['remotes_enabled' => false, 'pull_receipts' => true]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-remote-preferences.update'), ['from' => 'following', 'pull_receipts' => false])
        ->assertNotFound();

    expect($user->refresh()->pull_receipts)->toBeTrue();
});

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-remote-preferences.update'), ['from' => 'following', 'pull_receipts' => true])->assertUnauthorized();
});
