<?php

declare(strict_types=1);

use App\Models\User;

it('returns the signed-in user visibility settings', function (): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['ci', 'ai']]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'GET', route('api-user-commit-visibility.show'))
        ->assertOk()
        ->assertExactJson(['hidden_commit_channels' => ['ci', 'ai']]);
});

it('saves visibility settings across the API and the web', function (array $channels): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $other = User::factory()->create(['hidden_commit_channels' => ['ci']]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PATCH', route('api-user-commit-visibility.update'), [
        'hidden_commit_channels' => $channels,
        'user_id' => $other->id,
    ])
        ->assertOk()
        ->assertExactJson(['hidden_commit_channels' => $channels]);

    expect($user->refresh()->hidden_commit_channels)->toBe($channels);
    expect($other->refresh()->hidden_commit_channels)->toBe(['ci']);

    $this->actingAs($user)->get(route('user-commit-visibility.edit'))
        ->assertInertia(fn ($page) => $page->where('hiddenCommitChannels', $channels));
})->with([
    'all shown' => [[]],
    'all hidden' => [['cli', 'ci', 'ai']],
]);

it('rejects invalid API visibility choices without changing the setting', function (mixed $channels): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['ai']]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PATCH', route('api-user-commit-visibility.update'), ['hidden_commit_channels' => $channels])
        ->assertUnprocessable();

    expect($user->refresh()->hidden_commit_channels)->toBe(['ai']);
})->with([
    'null' => [null],
    'string' => ['cli'],
    'bot' => [['bot']],
    'unknown' => [['app']],
    'duplicate' => [['ai', 'ai']],
    'non-string' => [[false]],
    'associative' => [['source' => 'ci']],
]);

it('requires an explicit API visibility choice', function (): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['cli']]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PATCH', route('api-user-commit-visibility.update'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hidden_commit_channels');

    expect($user->refresh()->hidden_commit_channels)->toBe(['cli']);
});

it('rejects unsigned visibility requests', function (): void {
    $this->getJson(route('api-user-commit-visibility.show'))->assertUnauthorized();
    $this->patchJson(route('api-user-commit-visibility.update'), ['hidden_commit_channels' => ['cli']])
        ->assertUnauthorized();
});
