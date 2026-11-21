<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lists the apps', function (): void {
    $this->get(route('app.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/index'));
});

it('renders for signed in users too', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('app.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/index'));
});

it('describes the page to search engines and link previews without a Mac app', function (): void {
    $this->get(route('app.index'))
        ->assertOk()
        ->assertSee('<title>Apps - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('app.index').'">', false)
        ->assertSee('/dev/null for iOS, Android, and your terminal', false)
        ->assertDontSee('SoftwareApplication', false)
        ->assertDontSee('/images/apps/og.png', false)
        ->assertDontSee('/dev/null for Mac', false);
});

it('serves no Mac app page, release or download', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    'the old page' => '/mac',
    'the release' => '/api/v1/apps/mac',
    'the download' => '/downloads/DevNull.dmg',
]);
