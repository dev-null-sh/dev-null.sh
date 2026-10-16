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
        ->assertSee('<title>/dev/null: a social network for developers</title>', false)
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

it('automatically signs in a local developer without GitHub', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $this->get(route('home'))
        ->assertRedirectToRoute('log.show', Log::Following);

    $user = User::query()->where('github_id', 'local')->sole();

    expect($user->username)->toBe('local_dev')
        ->and($user->name)->toBe('Local Developer');

    $this->assertAuthenticatedAs($user);
});

it('reuses the local developer after logout', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $this->get(route('home'));

    $user = User::query()->where('github_id', 'local')->sole();

    $this->post(route('logout'), ['_token' => session()->token()])
        ->assertRedirectToRoute('home');
    $this->assertGuest();

    $this->get(route('home'))
        ->assertRedirectToRoute('log.show', Log::Following);

    $this->assertAuthenticatedAs($user);

    expect(User::query()->count())->toBe(1);
});

it('returns to the intended page after local sign in', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $user = User::factory()->create();

    $this->get(route('user.show', $user))->assertRedirectToRoute('home');

    $this->get(route('home'))->assertRedirectToRoute('user.show', $user);

    $this->assertAuthenticatedAs(User::query()->where('github_id', 'local')->sole());
});

it('does not automatically sign in locally when AUTO_LOGIN is disabled', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('log/show'));

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
});

it('does not automatically sign in outside the local environment', function (): void {
    app()->instance('env', 'production');
    config()->set('app.auto_login', true);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('log/show'));

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
});
