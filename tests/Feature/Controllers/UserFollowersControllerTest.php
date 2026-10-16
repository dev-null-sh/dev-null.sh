<?php

declare(strict_types=1);

use App\Models\User;

it("lists a user's followers", function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
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

it('lists them to guests, without anyone marked as followed', function (): void {
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->create());

    $response = $this->get(route('user-followers.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->has('users.data', 1)
            ->where('users.data.0.is_followed', false));
});

it('returns guests to the followers once they sign in', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-followers.index', $user))->assertOk();

    expect(session('url.intended'))->toBe(route('user-followers.index', $user));
});
