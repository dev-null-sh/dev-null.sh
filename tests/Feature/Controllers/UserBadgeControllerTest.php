<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\User;

it('renders the badge of a profile as a cacheable svg for guests', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $response = $this->get('/@octocat/badge.svg');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', escape: false)
        ->assertSee('/dev/null')
        ->assertSee('follow @octocat');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600')
        ->and($response->headers->get('ETag'))->not->toBeEmpty();
});

it('answers not modified while the badge is unchanged', function (): void {
    $user = User::factory()->create();

    $etag = (string) $this->get(route('user-badge.svg', $user))->headers->get('ETag');

    $this->get(route('user-badge.svg', $user), ['If-None-Match' => $etag])
        ->assertStatus(304);

    $this->get(route('user-badge.svg', $user), ['If-None-Match' => 'stale-etag'])
        ->assertOk();
});

it('renders follow badge message with user handle', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->get(route('user-badge.svg', $user))
        ->assertOk()
        ->assertSee('follow @octocat');
});

it('switches the theme and color styles', function (mixed $theme, string $expectedSelector): void {
    $user = User::factory()->create();

    $this->get(route('user-badge.svg', ['user' => $user, 'theme' => $theme]))
        ->assertOk()
        ->assertSee($expectedSelector, escape: false);
})->with([
    'light' => ['light', '.card{fill:#ffffff'],
    'dark' => ['dark', '.card{fill:#18181a'],
    'auto' => ['auto', '@media (prefers-color-scheme: dark){.card{fill:#18181a'],
    'unknown' => ['synthwave', '.card{fill:#ffffff'],
    'not a string' => [['dark'], '.card{fill:#ffffff'],
]);

it('renders user lane colors for light and dark themes', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-badge.svg', ['user' => $user, 'theme' => 'light']))
        ->assertOk()
        ->assertSee('.lane{fill:', escape: false);

    $this->get(route('user-badge.svg', ['user' => $user, 'theme' => 'dark']))
        ->assertOk()
        ->assertSee('.lane{fill:', escape: false);
});

it('throttles each badge per client', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->get(route('user-badge.svg', $user))->assertOk();
    }

    $this->get(route('user-badge.svg', $user))->assertTooManyRequests();
    $this->get(route('user-badge.svg', User::factory()->create()))->assertOk();
});

it('returns not found for unknown handles', function (): void {
    $this->get('/@missing/badge.svg')->assertNotFound();
});
