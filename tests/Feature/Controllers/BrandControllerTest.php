<?php

declare(strict_types=1);

use App\Models\User;

it('renders the brand page for guests', function (): void {
    $this->get(route('brand.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('brand/show'));
});

it('renders the brand page for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('brand.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('brand/show'));
});

it('describes the brand page to search engines and link previews', function (): void {
    $this->get(route('brand.show'))
        ->assertOk()
        ->assertSee('<title>Brand - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('brand.show').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});

it('ships every asset the brand page links to', function (): void {
    foreach (['mark', 'wordmark', 'logo'] as $kind) {
        foreach (['light', 'dark', 'black', 'white'] as $theme) {
            expect(public_path("images/brand/dev-null-{$kind}-{$theme}.svg"))->toBeFile()
                ->and(public_path("images/brand/dev-null-{$kind}-{$theme}.png"))->toBeFile();
        }
    }

    foreach (['wallpaper', 'banner', 'social'] as $kind) {
        foreach (['light', 'dark'] as $theme) {
            expect(public_path("images/brand/dev-null-{$kind}-{$theme}.svg"))->toBeFile()
                ->and(public_path("images/brand/dev-null-{$kind}-{$theme}.png"))->toBeFile();
        }
    }

    foreach (['mark', 'redirect', 'log'] as $kind) {
        foreach (['light', 'dark'] as $theme) {
            expect(public_path("images/brand/dev-null-wallpaper-{$kind}-{$theme}-desktop.png"))->toBeFile()
                ->and(public_path("images/brand/dev-null-wallpaper-{$kind}-{$theme}-desktop-preview.png"))->toBeFile()
                ->and(public_path("images/brand/dev-null-wallpaper-{$kind}-{$theme}-phone.png"))->toBeFile();
        }
    }

    expect(public_path('images/brand/dev-null-brand.zip'))->toBeFile();
});
