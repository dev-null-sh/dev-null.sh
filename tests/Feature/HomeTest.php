<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\User;

it('renders the trending log for guests', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', Log::Trending->value));
});

it('describes the landing page to search engines and link previews', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>/dev/null: the social network for developers</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta property="og:url" content="'.route('home').'">', false)
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"Organization"', false);
});

it('redirects authenticated users to the log', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('home'));

    $response->assertRedirectToRoute('log.show', Log::Following);
});

it('redirects authenticated users to the log they visited last', function (): void {
    $response = $this->actingAs(User::factory()->create(['log' => Log::Trending]))
        ->get(route('home'));

    $response->assertRedirectToRoute('log.show', Log::Trending);
});
