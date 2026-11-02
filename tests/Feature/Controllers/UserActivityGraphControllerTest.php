<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('renders the activity graph of a profile as a cacheable svg for guests', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    Commit::factory()->for($user)->create();

    $response = $this->get('/@octocat/graph.svg');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', escape: false)
        ->assertSee('@octocat');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600')
        ->and($response->headers->get('ETag'))->not->toBeEmpty();
});

it('answers not modified while the graph is unchanged', function (): void {
    $user = User::factory()->create();

    $etag = (string) $this->get(route('user-activity-graph.show', $user))->headers->get('ETag');

    $this->get(route('user-activity-graph.show', $user), ['If-None-Match' => $etag])
        ->assertStatus(304);

    Commit::factory()->for($user)->create();

    $this->get(route('user-activity-graph.show', $user), ['If-None-Match' => $etag])
        ->assertOk();
});

it('draws only approved commits, even for the author', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->get(route('user-activity-graph.show', $user))
        ->assertOk()
        ->assertSee('0 commits in the last year');
});

it('keeps the public graph identical for guests and viewers across a block', function (bool $viewerBlocks): void {
    $user = User::factory()->create();
    $viewer = User::factory()->create();

    Commit::factory()->for($user)->create();
    $viewer->muting()->attach($user);
    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $guest = $this->get(route('user-activity-graph.show', $user));
    $guest->assertOk()->assertSee('1 commit in the last year');

    $response = $this->actingAs($viewer)->get(route('user-activity-graph.show', $user));
    $response->assertOk();

    expect($response->getContent())->toBe($guest->getContent())
        ->and($response->headers->get('ETag'))->toBe($guest->headers->get('ETag'));
})->with([true, false]);

it('switches the theme', function (mixed $theme, string $card): void {
    $this->get(route('user-activity-graph.show', ['user' => User::factory()->create(), 'theme' => $theme]))
        ->assertOk()
        ->assertSee(".card{fill:{$card}", escape: false);
})->with([
    'light' => ['light', '#ffffff'],
    'dark' => ['dark', '#18181a'],
    'auto' => ['auto', '#ffffff'],
    'unknown' => ['neon', '#ffffff'],
    'not a string' => [['dark'], '#ffffff'],
]);

it('throttles each graph per client', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->get(route('user-activity-graph.show', $user))->assertOk();
    }

    $this->get(route('user-activity-graph.show', $user))->assertTooManyRequests();
    $this->get(route('user-activity-graph.show', User::factory()->create()))->assertOk();
});

it('returns not found for unknown handles', function (): void {
    $this->get('/@missing/graph.svg')->assertNotFound();
});
