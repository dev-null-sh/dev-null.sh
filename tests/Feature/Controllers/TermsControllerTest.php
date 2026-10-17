<?php

declare(strict_types=1);

use App\Models\User;

it('renders the terms of service for guests', function (): void {
    $this->get(route('terms.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('terms/show'));
});

it('renders the terms of service for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('terms.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('terms/show'));
});

it('describes the terms of service to search engines and link previews', function (): void {
    $this->get(route('terms.show'))
        ->assertOk()
        ->assertSee('<title>Terms of service - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('terms.show').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});
