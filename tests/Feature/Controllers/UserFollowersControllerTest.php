<?php

declare(strict_types=1);

use App\Models\User;

it("lists a user's followers", function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    $follower = User::factory()->create();
    $user->followers()->attach($follower);
    $user->following()->attach(User::factory()->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-followers.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'followers')
            ->has('users.data', 1)
            ->where('users.data.0.id', $follower->id)
            ->missing('users.data.0.email'));
});

it('marks the users the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $notFollowed = User::factory()->create();
    $followed = User::factory()->create();
    $user->followers()->attach([$notFollowed->id, $followed->id]);
    $viewer->following()->attach($followed);

    $response = $this->actingAs($viewer)
        ->get(route('user-followers.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.id', $followed->id)
            ->where('users.data.0.is_followed', true)
            ->where('users.data.1.id', $notFollowed->id)
            ->where('users.data.1.is_followed', false));
});

it('looks missing to people across a block', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->create());

    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('user-followers.index', $user));

    $response->assertNotFound();
})->with([true, false]);

it('marks who follows the viewer rather than the owner of the list', function (bool $ownList): void {
    $viewer = User::factory()->create();
    $user = $ownList ? $viewer : User::factory()->create();
    $follower = User::factory()->create();
    $user->followers()->attach($follower);
    if (! $ownList) {
        $viewer->followers()->attach($follower);
        $user->followers()->attach(User::factory()->create(), ['created_at' => now()->subMinute()]);
    }

    $this->actingAs($viewer)
        ->get(route('user-followers.index', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.id', $follower->id)
            ->where('users.data.0.follows_you', true)
            ->where('users.data.0.is_followed', false)
            ->when(! $ownList, fn ($page) => $page->where('users.data.1.follows_you', false)));
})->with([true, false]);

it('sends guests to the log, and back to the list once they sign in', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-followers.index', $user))->assertRedirect(route('home'));

    expect(session('url.intended'))->toBe(route('user-followers.index', $user));
});
