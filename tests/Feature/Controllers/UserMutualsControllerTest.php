<?php

declare(strict_types=1);

use App\Models\User;

it('lists the people a user follows who follow them back', function (): void {
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $user->following()->attach([$mutual->id, User::factory()->create()->id]);
    $user->followers()->attach([$mutual->id, User::factory()->create()->id]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'mutuals')
            ->has('users.data', 1)
            ->where('users.data.0.id', $mutual->id)
            ->missing('users.data.0.email'));
});

it('lists the newest follow first', function (): void {
    $user = User::factory()->create();
    $earlier = User::factory()->create();
    $later = User::factory()->create();
    $user->following()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->following()->attach($later);
    $user->followers()->attach([$earlier->id, $later->id]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.id', $later->id)
            ->where('users.data.1.id', $earlier->id));
});

it('marks the users the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach($followed);
    $viewer->following()->attach($followed);

    $response = $this->actingAs($viewer)
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.is_followed', true));
});

it('looks missing to people across a block', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();

    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('user-mutuals.index', $user));

    $response->assertNotFound();
})->with([true, false]);

it('sends guests to the log, and back to the list once they sign in', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-mutuals.index', $user))->assertRedirect(route('home'));

    expect(session('url.intended'))->toBe(route('user-mutuals.index', $user));
});
