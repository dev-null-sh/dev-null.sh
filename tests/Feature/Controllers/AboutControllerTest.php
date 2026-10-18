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
