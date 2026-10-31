<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use App\Models\UserList;

it('may add a developer to a list', function (): void {
    $user = User::factory()->create();
    $member = User::factory()->create(['handle' => 'octocat']);
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);

    $response = $this->actingAs($user)
        ->fromRoute('user.show', $member)
        ->post(route('user-list-member.store', [$list, $member]));

    $response->assertRedirectToRoute('user.show', $member)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Added @octocat to Laravel.']);

    expect($list->members->modelKeys())->toBe([$member->id])
        ->and(Notification::query()->count())->toBe(0);
});

it('adds a developer only once', function (): void {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $list = UserList::factory()->for($user)->create();

    $this->actingAs($user)->post(route('user-list-member.store', [$list, $member]));
    $this->actingAs($user)->post(route('user-list-member.store', [$list, $member]));

    expect($list->members()->count())->toBe(1);
});

it('may not add themselves or someone across a block', function (string $who): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create();
    $member = match ($who) {
        'themselves' => $user,
        'blocked' => tap(User::factory()->create(), fn (User $blocked) => $user->blocking()->attach($blocked)),
        'blocker' => tap(User::factory()->create(), fn (User $blocker) => $blocker->blocking()->attach($user)),
    };

    $this->actingAs($user)
        ->post(route('user-list-member.store', [$list, $member]))
        ->assertForbidden();

    expect($list->members()->count())->toBe(0);
})->with(['themselves', 'blocked', 'blocker']);

it('may not add more than 250 developers to a list', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create();
    $list->members()->attach(User::factory()->count(UserList::MAX_MEMBERS)->create());

    $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->post(route('user-list-member.store', [$list, User::factory()->create()]))
        ->assertSessionHasErrors(['list' => 'A list holds up to 250 developers.']);

    expect($list->members()->count())->toBe(UserList::MAX_MEMBERS);
});

it('may remove a developer from a list', function (): void {
    $user = User::factory()->create();
    $member = User::factory()->create(['handle' => 'octocat']);
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);

    $list->members()->attach($member);

    $response = $this->actingAs($user)
        ->fromRoute('user-list.show', $list)
        ->delete(route('user-list-member.destroy', [$list, $member]));

    $response->assertRedirectToRoute('user-list.show', $list)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Removed @octocat from Laravel.']);

    expect($list->members()->count())->toBe(0);
});

it('may not change the list of someone else', function (string $method, string $route): void {
    $list = UserList::factory()->create();
    $member = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, [$list, $member]))
        ->assertNotFound();

    expect($list->members()->count())->toBe(0);
})->with([
    'add' => ['post', 'user-list-member.store'],
    'remove' => ['delete', 'user-list-member.destroy'],
]);
