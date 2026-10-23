<?php

declare(strict_types=1);

use App\Models\User;

it('may dismiss a suggested user', function (): void {
    $user = User::factory()->create();
    $dismissed = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', 'latest')
        ->post(route('suggestion-dismissal.store', $dismissed));

    $response->assertRedirectToRoute('log.show', 'latest');

    expect($user->dismissedSuggestions()->pluck('users.id')->all())->toBe([$dismissed->id]);
});

it('may dismiss the same user twice', function (): void {
    $user = User::factory()->create();
    $dismissed = User::factory()->create();

    $user->dismissedSuggestions()->attach($dismissed);

    $this->actingAs($user)
        ->post(route('suggestion-dismissal.store', $dismissed))
        ->assertRedirect();

    expect($user->dismissedSuggestions()->count())->toBe(1);
});

it('may not dismiss themselves', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('suggestion-dismissal.store', $user))
        ->assertForbidden();

    expect($user->dismissedSuggestions()->count())->toBe(0);
});

it('requires authentication to dismiss', function (): void {
    $dismissed = User::factory()->create();

    $this->post(route('suggestion-dismissal.store', $dismissed))
        ->assertRedirectToRoute('home');
});
