<?php

declare(strict_types=1);

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('may dismiss a suggested user', function (): void {
    $user = User::factory()->create();
    $dismissed = User::factory()->create();
    Event::fake([UsersUpdated::class]);

    $response = $this->actingAs($user)
        ->fromRoute('log.show', 'latest')
        ->post(route('suggestion-dismissal.store', $dismissed));

    $response->assertRedirectToRoute('log.show', 'latest');

    expect($user->dismissedSuggestions()->pluck('users.id')->all())->toBe([$dismissed->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Suggestions
        && $event->userIds === [$user->id] && $event->broadcastWith() === []);
});

it('may dismiss the same user twice', function (): void {
    $user = User::factory()->create();
    $dismissed = User::factory()->create();

    $user->dismissedSuggestions()->attach($dismissed);
    Event::fake([UsersUpdated::class]);

    $this->actingAs($user)
        ->post(route('suggestion-dismissal.store', $dismissed))
        ->assertRedirect();

    expect($user->dismissedSuggestions()->count())->toBe(1);
    Event::assertNotDispatched(UsersUpdated::class);
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
