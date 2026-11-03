<?php

declare(strict_types=1);

use App\Models\User;

it('renders the partners page for guests', function (): void {
    $this->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('partner/index'));
});

it('renders the partners page for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('partner/index'));
});

it('describes the partners page to search engines and link previews', function (): void {
    $this->get(route('partner.index'))
        ->assertOk()
        ->assertSee('<title>Partners - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('partner.index').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});
