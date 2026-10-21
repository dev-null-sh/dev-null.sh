<?php

declare(strict_types=1);

use App\Models\User;

it('saves a private translation language only for the signed-in user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['translation_language' => 'de']);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-translation-language.update'), ['translation_language' => 'ja', 'user_id' => $other->id])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Translation language saved.']);

    expect($user->refresh()->translation_language)->toBe('ja');
    expect($user->getRawOriginal('translation_language'))->not->toBe('ja');
    expect($user->toArray())->not->toHaveKey('translation_language');
    expect($other->refresh()->translation_language)->toBe('de');
});

it('clears the override to follow the browser language', function (mixed $language): void {
    $user = User::factory()->create(['translation_language' => 'ja']);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-translation-language.update'), ['translation_language' => $language])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->translation_language)->toBeNull();
})->with([null, '']);

it('rejects invalid translation languages without changing the preference', function (mixed $language): void {
    $user = User::factory()->create(['translation_language' => 'ja']);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-translation-language.update'), ['translation_language' => $language])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('translation_language');

    expect($user->refresh()->translation_language)->toBe('ja');
})->with(['invented', 'ja-JP', 'JA', 42, [['ja']]]);

it('requires an explicit preference when saving', function (): void {
    $user = User::factory()->create(['translation_language' => 'ja']);

    $this->actingAs($user)->put(route('user-translation-language.update'))
        ->assertSessionHasErrors('translation_language');

    expect($user->refresh()->translation_language)->toBe('ja');
});

it('requires authentication', function (): void {
    $this->put(route('user-translation-language.update'), ['translation_language' => 'ja'])
        ->assertRedirectToRoute('home');
});
