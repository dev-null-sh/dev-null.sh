<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

it('shows an unclaimed developer to anyone with the link, but never where they are', function (): void {
    Profile::factory()->create([
        'handle' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
    ]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile/unclaimed')
            ->where('profile', [
                'handle' => 'octocat',
                'name' => 'The Octocat',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
                'bio' => 'I am the Octocat.',
            ]));
});

it('previews the page of an unclaimed developer for guests, kept out of search engines', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat', 'name' => 'The Octocat', 'bio' => null]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<meta property="og:title" content="The Octocat (@octocat) on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="@octocat isn&#039;t on /dev/null yet.">', false)
        ->assertSee(e(route('user-open-graph-image.show', ['profile' => 'octocat', 'v' => $profile->openGraphImageVersion()])), false);

    $this->actingAs(User::factory()->create())
        ->get('/@octocat')
        ->assertOk()
        ->assertDontSee('The Octocat (@octocat) on /dev/null', false);
});

it('moves for good to the handle of the unclaimed developer in its own case', function (): void {
    Profile::factory()->create(['handle' => 'OctoCat']);

    $this->get('/@octocat?tab=mentions')->assertRedirect('/@OctoCat?tab=mentions')->assertStatus(301);
});

it('shows a member their own page, not the unclaimed one, once they signed in', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/show')->where('user.id', $user->id));
});
