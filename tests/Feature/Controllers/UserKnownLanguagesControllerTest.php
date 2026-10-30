<?php

declare(strict_types=1);

use App\Models\User;

it('saves private known languages only for the signed-in user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['known_languages' => ['de']]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-known-languages.update'), ['known_languages' => ['ja', 'pt'], 'user_id' => $other->id])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Languages saved.']);

    expect($user->refresh()->known_languages)->toBe(['ja', 'pt']);
    expect($user->getRawOriginal('known_languages'))->not->toContain('"ja"');
    expect($user->toArray())->not->toHaveKey('known_languages');
    expect($other->refresh()->known_languages)->toBe(['de']);
});

it('clears the known languages', function (mixed $languages): void {
    $user = User::factory()->create(['known_languages' => ['ja']]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-known-languages.update'), ['known_languages' => $languages])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->known_languages)->toBeNull();
})->with([null, '', [[]]]);

it('rejects invalid known languages without changing the preference', function (mixed $languages, string $error): void {
    $user = User::factory()->create(['known_languages' => ['ja']]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-known-languages.update'), ['known_languages' => $languages])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors($error);

    expect($user->refresh()->known_languages)->toBe(['ja']);
})->with([
    'unknown code' => [['invented'], 'known_languages.0'],
    'regional code' => [['ja-JP'], 'known_languages.0'],
    'uppercase code' => [['JA'], 'known_languages.0'],
    'number' => [[42], 'known_languages.0'],
    'duplicate' => [['de', 'de'], 'known_languages.0'],
    'single string' => ['ja', 'known_languages'],
    'too many' => [['en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'ja', 'ko', 'zh', 'ru'], 'known_languages'],
]);

it('requires an explicit list when saving', function (): void {
    $user = User::factory()->create(['known_languages' => ['ja']]);

    $this->actingAs($user)->put(route('user-known-languages.update'))
        ->assertSessionHasErrors('known_languages');

    expect($user->refresh()->known_languages)->toBe(['ja']);
});

it('requires authentication', function (): void {
    $this->put(route('user-known-languages.update'), ['known_languages' => ['ja']])
        ->assertRedirectToRoute('home');
});
