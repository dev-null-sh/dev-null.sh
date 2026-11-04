<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserList;

it('may pin a list', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);

    $response = $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->put(route('user-list-pin.update', $list));

    $response->assertRedirectToRoute('user-list.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pinned Laravel next to your logs.']);

    expect($list->refresh()->isPinned())->toBeTrue();
});

it('keeps a pinned list where it was when pinned again', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create(['pinned_at' => now()->subDay()]);
    UserList::factory()->for($user)->count(UserList::MAX_PINNED - 1)->pinned()->create();

    $this->actingAs($user)
        ->put(route('user-list-pin.update', $list))
        ->assertSessionHasNoErrors();

    expect($list->refresh()->pinned_at->isSameDay(now()->subDay()))->toBeTrue();
});

it('may not pin more than two lists', function (): void {
    $user = User::factory()->create();
    UserList::factory()->for($user)->count(UserList::MAX_PINNED)->pinned()->create();
    $list = UserList::factory()->for($user)->create();

    $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->put(route('user-list-pin.update', $list))
        ->assertSessionHasErrors(['list' => 'You can pin up to 2 lists. Unpin one first.']);

    expect($list->refresh()->isPinned())->toBeFalse();
});

it('may unpin a list', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->pinned()->create(['name' => 'Laravel']);

    $response = $this->actingAs($user)
        ->fromRoute('user-list.show', $list)
        ->delete(route('user-list-pin.destroy', $list));

    $response->assertRedirectToRoute('user-list.show', $list)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unpinned Laravel.']);

    expect($list->refresh()->isPinned())->toBeFalse();
});

it('may not pin or unpin the list of someone else', function (string $method, string $route): void {
    $list = UserList::factory()->pinned()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $list))
        ->assertNotFound();
})->with([
    'pin' => ['put', 'user-list-pin.update'],
    'unpin' => ['delete', 'user-list-pin.destroy'],
]);
