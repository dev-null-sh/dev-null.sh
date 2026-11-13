<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('saves the translation language only for the user, and answers like me', function (): void {
    $other = User::factory()->create(['translation_language' => 'de']);
    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-translation-language.update'), ['translation_language' => 'ja', 'user_id' => $other->id]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonMissingPath('data.translation_language')
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh()->translation_language)->toBe('ja')
        ->and($other->refresh()->translation_language)->toBe('de');
});

it('clears the language to follow the device', function (mixed $language): void {
    $this->user->update(['translation_language' => 'ja']);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-translation-language.update'), ['translation_language' => $language])->assertOk();

    expect($this->user->refresh()->translation_language)->toBeNull();
})->with([null, '']);

it('rejects invalid translation languages without changing the preference', function (array $data): void {
    $this->user->update(['translation_language' => 'ja']);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-translation-language.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('translation_language');

    expect($this->user->refresh()->translation_language)->toBe('ja');
})->with([
    'an invented language' => [['translation_language' => 'invented']],
    'a locale' => [['translation_language' => 'ja-JP']],
    'an uppercase code' => [['translation_language' => 'JA']],
    'a number' => [['translation_language' => 42]],
    'a list' => [['translation_language' => ['ja']]],
    'no language' => [[]],
]);

it('requires a signed request', function (): void {
    $this->putJson(route('api-user-translation-language.update'), ['translation_language' => 'ja'])->assertUnauthorized();

    expect($this->user->refresh()->translation_language)->toBeNull();
});
