<?php

declare(strict_types=1);

use App\Models\User;

it('pushes everything until the user turns it off', function (): void {
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-user-settings.show'))
        ->assertOk()
        ->assertJsonPath('data.push', [
            'follows' => true,
            'replies' => true,
            'mentions' => true,
            'direct_remotes' => true,
            'org_remotes' => true,
        ]);
});

it('saves what the user wants pushed, and answers as the settings show it', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $preferences = [
        'follows' => false,
        'replies' => true,
        'mentions' => true,
        'direct_remotes' => true,
        'org_remotes' => false,
    ];

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-push-preferences.update'), $preferences);

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => $preferences]);

    expect(signedApiRequest($keyPair, 'GET', route('api-user-settings.show'))->json('data.push'))->toBe($preferences)
        ->and($user->refresh())
        ->push_follows->toBeFalse()
        ->push_org_remotes->toBeFalse()
        ->push_replies->toBeTrue();
});

it('needs every switch, each on or off', function (array $change, string $field): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-push-preferences.update'), [
        'follows' => false,
        'replies' => false,
        'mentions' => false,
        'direct_remotes' => false,
        'org_remotes' => false,
        ...$change,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($user->refresh()->push_follows)->toBeTrue();
})->with([
    'no follows' => [['follows' => null], 'follows'],
    'replies not a yes or no' => [['replies' => 'maybe'], 'replies'],
    'no org remotes' => [['org_remotes' => null], 'org_remotes'],
]);

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-push-preferences.update'), ['follows' => false])->assertUnauthorized();
});
