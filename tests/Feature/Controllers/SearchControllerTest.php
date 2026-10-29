<?php

declare(strict_types=1);

it('renders the search page with the trimmed query, to guests too', function (): void {
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
