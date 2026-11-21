<?php

declare(strict_types=1);

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('saves the event theme only for the signed-in user', function (string $choice): void {
    Event::fake([UsersUpdated::class]);
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->fromRoute('appearance.edit')
        ->put(route('user-event-theme.update'), ['choice' => $choice, 'user_id' => $other->id])
        ->assertRedirectToRoute('appearance.edit');

    expect($user->refresh()->event_theme)->toBe($choice)
        ->and($user->toArray())->not->toHaveKey('event_theme')
        ->and($other->refresh()->event_theme)->toBeNull();

    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->userIds === [$user->id] && $event->update === UserUpdate::Account);
})->with(['auto', 'none', 'halloween', 'christmas', 'newyear']);

it('rejects missing or unknown choices without changing the saved one', function (array $data): void {
    $user = User::factory()->create(['event_theme' => 'christmas']);

    $this->actingAs($user)->fromRoute('appearance.edit')
        ->put(route('user-event-theme.update'), $data)
        ->assertRedirectToRoute('appearance.edit')
        ->assertSessionHasErrors('choice');

    expect($user->refresh()->event_theme)->toBe('christmas');
})->with(['missing' => [[]], 'unknown' => [['choice' => 'easter']], 'array' => [['choice' => ['auto']]]]);

it('requires authentication', function (): void {
    $this->put(route('user-event-theme.update'), ['choice' => 'none'])
        ->assertRedirectToRoute('home');
});
