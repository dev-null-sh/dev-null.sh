<?php

declare(strict_types=1);

use App\Models\User;

it('saves a boolean automatic translation preference only for the signed-in user', function (bool $enabled): void {
    $user = User::factory()->create(['automatic_translation' => ! $enabled]);
    $other = User::factory()->create(['automatic_translation' => ! $enabled]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-automatic-translation.update'), ['enabled' => $enabled, 'user_id' => $other->id])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Automatic translation preference saved.']);

    expect($user->refresh()->automatic_translation)->toBe($enabled)
        ->and($user->getRawOriginal('automatic_translation'))->toBe($enabled)
        ->and($user->toArray())->not->toHaveKey('automatic_translation')
        ->and($other->refresh()->automatic_translation)->toBe(! $enabled);

    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page
        ->where('auth.automaticTranslation', $enabled)
        ->missing('auth.user.automatic_translation'));
})->with([true, false]);

it('keeps automatic translations enabled for existing accounts and guests', function (): void {
    $user = User::factory()->create();

    expect($user->refresh()->automatic_translation)->toBeNull();

    $this->get(route('log.show', 'trending'))->assertInertia(fn ($page) => $page->where('auth.automaticTranslation', true));
    $this->actingAs($user)->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page->where('auth.automaticTranslation', true));
});

it('rejects missing or invalid preferences without changing the saved choice', function (array $data): void {
    $user = User::factory()->create(['automatic_translation' => false]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-automatic-translation.update'), $data)
        ->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('enabled');

    expect($user->refresh()->automatic_translation)->toBeFalse();
})->with(['missing' => [[]], 'null' => [['enabled' => null]], 'text' => [['enabled' => 'invalid']], 'array' => [['enabled' => []]]]);

it('requires authentication', function (): void {
    $this->put(route('user-automatic-translation.update'), ['enabled' => false])
        ->assertRedirectToRoute('home');
});
