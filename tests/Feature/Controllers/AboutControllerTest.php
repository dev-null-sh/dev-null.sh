<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake(['api.github.com/repos/*/contributors*' => Http::response([
        ['login' => 'contributor', 'type' => 'User', 'contributions' => 12],
    ])]);
});

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

it('passes GitHub contributors to the about page', function (): void {
    $this->get(route('about.show'))->assertInertia(fn ($page) => $page
        ->has('contributors', 1)
        ->where('contributors.0.login', 'contributor')
        ->where('contributors.0.commits', 12));
});
