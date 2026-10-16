<?php

declare(strict_types=1);

use App\Models\User;

it("graphs a user's followers as a network", function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $follower = User::factory()->create();
    $other = User::factory()->create();
    $user->followers()->attach($follower->id, ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);
    $user->followers()->attach($other->id, ['created_at' => now(), 'updated_at' => now()]);
    $follower->following()->attach($other);
    $user->following()->attach($follower);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-followers-graph.show', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/followers-graph')
            ->has('nodes', 2)
            ->where('nodes.0.username', $other->username)
            ->where('nodes.0.followed_back', false)
            ->where('nodes.1.username', $follower->username)
            ->where('nodes.1.followed_back', true)
            ->has('edges', 1)
            ->where('edges.0.source', $follower->id)
            ->where('edges.0.target', $other->id)
            ->where('totalFollowers', 2)
            ->missing('nodes.0.email'));
});

it('caps the nodes drawn for large follower counts', function (): void {
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->count(3)->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-followers-graph.show', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totalFollowers', 3)
            ->where('limit', 250));
});

it('looks missing to people across a block', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->create());

    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('user-followers-graph.show', $user));

    $response->assertNotFound();
})->with([true, false]);

it('graphs them to guests', function (): void {
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->create());

    $response = $this->get(route('user-followers-graph.show', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/followers-graph')
            ->has('nodes', 1));
});

it('returns guests to the graph once they sign in', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-followers-graph.show', $user))->assertOk();

    expect(session('url.intended'))->toBe(route('user-followers-graph.show', $user));
});

it('previews the graph to guests without letting search engines index it', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'name' => 'The Octocat']);

    $this->get(route('user-followers-graph.show', $user))
        ->assertOk()
        ->assertSee('<title>Follower network of The Octocat (@octocat) on /dev/null</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('user-followers-graph.show', $user).'">', false)
        ->assertSee('<meta property="og:image" content="'.route('user-open-graph-image.show', $user).'?v=', false);
});

it('keeps the default link preview of the graph for signed-in users', function (): void {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('user-followers-graph.show', $user))
        ->assertOk()
        ->assertDontSee('<meta name="robots"', false)
        ->assertDontSee('og:url', false);
});
