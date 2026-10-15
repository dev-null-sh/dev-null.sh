<?php

declare(strict_types=1);

use App\Models\User;

it('lists who a user is following', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach(User::factory()->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-following.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'following')
            ->where('user.username', 'octocat')
            ->has('users.data', 1)
            ->where('users.data.0.id', $followed->id));
});

it('marks the users the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $notFollowed = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach([$notFollowed->id, $followed->id]);
    $viewer->following()->attach($followed);

    $response = $this->actingAs($viewer)
        ->get(route('user-following.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.id', $followed->id)
            ->where('users.data.0.is_followed', true)
            ->where('users.data.1.id', $notFollowed->id)
            ->where('users.data.1.is_followed', false));
});

it('requires authentication', function (): void {
    $user = User::factory()->create();

    $response = $this->get(route('user-following.index', $user));

    $response->assertRedirectToRoute('home');
});
