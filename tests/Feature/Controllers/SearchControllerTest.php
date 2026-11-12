<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('sends guests to the log, and back to the search once they sign in', function (): void {
    $this->app['auth']->forgetGuards();

    $this->get('/search?q=laravel')->assertRedirect(route('home'));

    expect(session('url.intended'))->toBe(url('/search?q=laravel'));
});

it('renders the search page with the trimmed query', function (): void {
    $this->get('/search?q=%20caching%20with%20mongodb%20')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('search/index')
            ->where('query', 'caching with mongodb'));
});

it('renders an empty query without one', function (): void {
    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('query', ''));
});

it('rejects a query longer than 100 characters', function (): void {
    $this->get('/search?q='.str_repeat('a', 101))
        ->assertRedirect()
        ->assertSessionHasErrors('q');
});

it('keeps search result pages out of search engines while their links are followed', function (): void {
    $this->get('/search?q=laravel')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});
