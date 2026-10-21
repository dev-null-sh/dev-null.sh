<?php

declare(strict_types=1);

use App\Models\User;

it('saves a translation language for the signed-in user and answers like me', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['translation_language' => 'de']);
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-translation-language.update'), [
        'translation_language' => 'ja',
        'user_id' => $other->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.translation_language', 'ja')
        ->assertExactJson(signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->json());

    expect($user->refresh()->translation_language)->toBe('ja')
        ->and($other->refresh()->translation_language)->toBe('de');

    signedApiRequest($keyPair, 'GET', route('api-user.show', $user))
        ->assertOk()
        ->assertJsonMissingPath('data.translation_language');
});

it('clears the language so the request language is used', function (): void {
    $user = User::factory()->create(['translation_language' => 'ja']);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-translation-language.update'), ['translation_language' => null])
        ->assertOk()
        ->assertJsonPath('data.translation_language', null);

    expect($user->refresh()->translation_language)->toBeNull();
});

it('rejects a language that is not a primary language code', function (mixed $language): void {
    $user = User::factory()->create(['translation_language' => 'ja']);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-user-translation-language.update'), ['translation_language' => $language])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('translation_language');

    expect($user->refresh()->translation_language)->toBe('ja');
})->with(['invented', 'ja-JP', 'JA']);

it('requires the preference to be present', function (): void {
    $user = User::factory()->create(['translation_language' => 'ja']);

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-translation-language.update'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('translation_language');

    expect($user->refresh()->translation_language)->toBe('ja');
});

it('refuses an unsigned preference', function (): void {
    $this->putJson(route('api-user-translation-language.update'), ['translation_language' => 'ja'])
        ->assertUnauthorized();
});
