<?php

declare(strict_types=1);

use App\Models\User;

it('renders the about page for guests', function (): void {
    $this->get(route('about.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('about/show'));
});

it('renders the about page for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('about.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('about/show'));
});

it('describes the about page to search engines and link previews', function (): void {
    $this->get(route('about.show'))
        ->assertOk()
        ->assertSee('<title>About - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('about.show').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});

it('links each contributor who is on /dev/null to their profile, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '14284867', 'username' => 'renamed-on-devnull']);

    $this->get(route('about.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contributors.0', ['login' => 'xHeaven', 'commits' => 199, 'username' => 'renamed-on-devnull'])
            ->where('contributors.1', ['login' => 'nunomaduro', 'commits' => 176, 'username' => null]));
});
