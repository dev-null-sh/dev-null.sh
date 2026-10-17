<?php

declare(strict_types=1);

use App\Models\User;

it('renders the privacy policy for guests', function (): void {
    $this->get(route('privacy.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('privacy/show'));
});

it('renders the privacy policy for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('privacy.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('privacy/show'));
});

it('describes the privacy policy to search engines and link previews', function (): void {
    $this->get(route('privacy.show'))
        ->assertOk()
        ->assertSee('<title>Privacy policy - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('privacy.show').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});
