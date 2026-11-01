<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('lists who a user is following', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach(User::factory()->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-following.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'following')
            ->where('user.handle', 'octocat')
            ->has('users.data', 1)
            ->where('users.data.0.id', $followed->id));
});

it('lists the organizations a user follows apart, newest follow first, marking those the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $person = User::factory()->create();
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $bref = Organization::factory()->create(['handle' => 'brefphp']);
    $user->following()->attach($person);
    $user->followedOrganizations()->attach($bref, ['created_at' => now()->subDay()]);
    $user->followedOrganizations()->attach($laravel, ['created_at' => now()]);
    $viewer->followedOrganizations()->attach($bref);

    $this->actingAs($viewer)->get(route('user-following.index', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $person->id)
            ->has('organizations.data', 2)
            ->where('organizations.data.0.id', $laravel->id)
            ->where('organizations.data.0.handle', 'laravel')
            ->where('organizations.data.0.is_followed', false)
            ->where('organizations.data.1.id', $bref->id)
            ->where('organizations.data.1.is_followed', true)
            ->missing('organizations.data.0.pivot')
            ->missing('organizations.data.0.github_id'));
});

it('pages through the organizations with a cursor of their own, 10 at a time', function (): void {
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(11)->create();

    foreach ($organizations as $index => $organization) {
        $user->followedOrganizations()->attach($organization, ['created_at' => now()->subMinutes($index)]);
    }

    $viewer = User::factory()->create();

    $first = $this->actingAs($viewer)->get(route('user-following.index', $user));

    $first->assertInertia(fn ($page) => $page->has('organizations.data', 10));

    $next = $first->viewData('page')['props']['organizations']['next_page_url'];

    expect($next)->toContain('organizations_cursor=');

    $this->get($next)->assertInertia(fn ($page) => $page
        ->has('organizations.data', 1)
        ->where('organizations.data.0.id', $organizations->last()->id));
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

it('looks missing to people across a block', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $user->following()->attach(User::factory()->create());

    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('user-following.index', $user));

    $response->assertNotFound();
})->with([true, false]);

it('sends guests to the log, and back to the list once they sign in', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-following.index', $user))->assertRedirect(route('home'));

    expect(session('url.intended'))->toBe(route('user-following.index', $user));
});
