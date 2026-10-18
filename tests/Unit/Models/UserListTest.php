<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserList;

test('to array', function (): void {
    $list = UserList::factory()->create()->refresh()->loadCount('members');

    expect(array_keys($list->toArray()))
        ->toBe([
            'id',
            'name',
            'pinned_at',
            'members_count',
        ]);
});

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create();

    expect($list->user->is($user))->toBeTrue()
        ->and($list->isOwnedBy($user))->toBeTrue()
        ->and($list->isOwnedBy(User::factory()->create()))->toBeFalse();
});

it('has members', function (): void {
    $list = UserList::factory()->create();
    $member = User::factory()->create();

    $list->members()->attach($member);

    expect($list->members->modelKeys())->toBe([$member->id]);
});

it('may be pinned', function (): void {
    expect(UserList::factory()->pinned()->create()->isPinned())->toBeTrue()
        ->and(UserList::factory()->create()->isPinned())->toBeFalse();
});

it('lists the pinned lists of a user in the order they were pinned', function (): void {
    $user = User::factory()->create();

    $second = UserList::factory()->for($user)->create(['name' => 'Second', 'pinned_at' => now()]);
    $first = UserList::factory()->for($user)->create(['name' => 'First', 'pinned_at' => now()->subMinute()]);
    UserList::factory()->for($user)->create();
    UserList::factory()->pinned()->create();

    expect($user->pinnedLists()->modelKeys())->toBe([$first->id, $second->id]);
});

it('goes away with its owner', function (): void {
    $list = UserList::factory()->create();

    $list->user->delete();

    expect(UserList::query()->count())->toBe(0);
});
