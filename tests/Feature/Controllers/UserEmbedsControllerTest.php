<?php

declare(strict_types=1);

use App\Models\User;

it('saves whether others may embed the commits of the signed-in user only', function (bool $allowed): void {
    $user = User::factory()->create(['allows_embeds' => ! $allowed]);
    $other = User::factory()->create(['allows_embeds' => ! $allowed]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-embeds.update'), ['enabled' => $allowed, 'user_id' => $other->id])
        ->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Embeds preference saved.']);

    expect($user->refresh()->allows_embeds)->toBe($allowed)
        ->and($user->toArray())->not->toHaveKey('allows_embeds')
        ->and($other->refresh()->allows_embeds)->toBe(! $allowed);

    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page
        ->where('auth.allowsEmbeds', $allowed)
        ->missing('auth.user.allows_embeds'));
})->with([true, false]);

it('allows embeds by default', function (): void {
    $user = User::factory()->create();

    expect($user->refresh()->allows_embeds)->toBeTrue();

    $this->actingAs($user)->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page->where('auth.allowsEmbeds', true));
});

it('rejects missing or invalid preferences without changing the saved choice', function (array $data): void {
    $user = User::factory()->create(['allows_embeds' => false]);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-embeds.update'), $data)
        ->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('enabled');

    expect($user->refresh()->allows_embeds)->toBeFalse();
})->with(['missing' => [[]], 'null' => [['enabled' => null]], 'text' => [['enabled' => 'invalid']], 'array' => [['enabled' => []]]]);

it('requires authentication', function (): void {
    $this->put(route('user-embeds.update'), ['enabled' => false])
        ->assertRedirectToRoute('home');
});
