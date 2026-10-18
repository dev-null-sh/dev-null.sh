<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Models\UserList;

it('renders the lists of the user', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);
    UserList::factory()->for($user)->create(['name' => 'Bun']);
    UserList::factory()->create();

    $list->members()->attach(User::factory()->count(2)->create());

    $response = $this->actingAs($user)->get(route('user-list.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-list/index')
            ->has('lists', 2)
            ->where('lists.0.name', 'Bun')
            ->where('lists.0.members_count', 0)
            ->where('lists.1.name', 'Laravel')
            ->where('lists.1.members_count', 2)
            ->where('maxLists', UserList::MAX_PER_USER)
            ->where('maxPinned', UserList::MAX_PINNED));
});

it('requires authentication to see lists', function (): void {
    $this->get(route('user-list.index'))->assertRedirectToRoute('home');
});

it('may create a list', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->post(route('user-list.store'), ['name' => '  Laravel   core ']);

    $response->assertRedirectToRoute('user-list.index')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Created Laravel core. Add developers to it from their profiles.',
        ]);

    expect($user->lists()->sole()->name)->toBe('Laravel core');
});

it('requires a name of at most 40 characters', function (string $name): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->post(route('user-list.store'), ['name' => $name]);

    $response->assertRedirectToRoute('user-list.index')
        ->assertSessionHasErrors('name');

    expect($user->lists()->count())->toBe(0);
})->with([
    'empty' => '',
    'too long' => str_repeat('a', 41),
]);

it('may not have more than ten lists', function (): void {
    $user = User::factory()->create();
    UserList::factory()->for($user)->count(UserList::MAX_PER_USER)->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-list.index')
        ->post(route('user-list.store'), ['name' => 'One more']);

    $response->assertSessionHasErrors(['name' => 'You can have up to 10 lists. Delete one to make room.']);

    expect($user->lists()->count())->toBe(UserList::MAX_PER_USER);
});

it('renders the commits of the members of a list newest first', function (): void {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);
    $pinned = UserList::factory()->for($user)->pinned()->create(['name' => 'Pinned']);

    $list->members()->attach([$member->id, $other->id]);
    $user->following()->attach(User::factory()->create());

    Commit::factory()->for($member)->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($other)->create(['body' => 'Newer']);
    Commit::factory()->for($member)->create(['status' => CommitStatus::Pending]);
    Commit::factory()->for($member)->create(['parent_id' => Commit::factory()->for($other)->create(['created_at' => now()->subHour()])->id]);
    Commit::factory()->for($user)->create(['body' => 'Mine']);
    Commit::factory()->create(['body' => 'Stranger']);

    $response = $this->actingAs($user)->get(route('user-list.show', $list));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-list/show')
            ->where('list.id', $list->id)
            ->where('list.name', 'Laravel')
            ->where('list.members_count', 2)
            ->has('members', 2)
            ->has('pinnedLists', 1)
            ->where('pinnedLists.0.id', $pinned->id)
            ->has('commits.data', 3)
            ->where('commits.data.0.body', 'Newer')
            ->where('commits.data.1.body', 'Older')
            ->missing('members.0.email'));
});

it('leaves out commits of muted and blocked members', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $list = UserList::factory()->for($user)->create();

    $list->members()->attach([$muted->id, $blocked->id]);
    $user->muting()->attach($muted);
    $blocked->blocking()->attach($user);

    Commit::factory()->for($muted)->create();
    Commit::factory()->for($blocked)->create();

    $response = $this->actingAs($user)->get(route('user-list.show', $list));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 0));
});

it('does not show the list of someone else', function (): void {
    $list = UserList::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('user-list.show', $list))
        ->assertNotFound();
});

it('may delete a list', function (): void {
    $user = User::factory()->create();
    $list = UserList::factory()->for($user)->create(['name' => 'Laravel']);
    $member = User::factory()->create();

    $list->members()->attach($member);

    $response = $this->actingAs($user)->delete(route('user-list.destroy', $list));

    $response->assertRedirectToRoute('user-list.index')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Deleted Laravel.']);

    expect($list->exists())->toBeFalse()
        ->and($member->exists())->toBeTrue();
});

it('may not delete the list of someone else', function (): void {
    $list = UserList::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('user-list.destroy', $list))
        ->assertNotFound();

    expect($list->exists())->toBeTrue();
});
