<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Commit;
use App\Models\User;

it('renders a link preview image of a commit for guests', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->once()->with('https://avatars.githubusercontent.com/u/583231')->andReturn(fakeAvatar());

    $user = User::factory()->create(['avatar_url' => 'https://avatars.githubusercontent.com/u/583231']);
    $commit = Commit::factory()->for($user)->create();

    $response = $this->get(route('commit-open-graph-image.show', [$user, $commit]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600')
        ->and(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('renders a commit whose author avatar cannot be downloaded', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->andReturnNull();

    $commit = Commit::factory()->create();

    $response = $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('reuses the rendered image until the public commit changes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());

    $commit = Commit::factory()->create();

    $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]))->assertOk();
    Commit::factory()->for($commit, 'parent')->pending()->create();
    $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]))->assertOk();

    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());
    Commit::factory()->for($commit, 'parent')->create();

    $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]))->assertOk();
});

it('returns not found for commits that are not approved', function (Closure $factory): void {
    $commit = $factory();

    $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]))->assertNotFound();
})->with([
    'pending' => [fn (): Commit => Commit::factory()->pending()->create()],
    'rejected' => [fn (): Commit => Commit::factory()->rejected()->create()],
]);

it('returns not found for a commit under another handle', function (): void {
    $commit = Commit::factory()->create();

    $this->get(route('commit-open-graph-image.show', [User::factory()->create(), $commit]))->assertNotFound();
});

it('returns not found for unknown commits', function (): void {
    $user = User::factory()->create();

    $this->get("/@{$user->handle}/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3/og.png")->assertNotFound();
});

it('returns not found for deleted commits without rendering an image', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('avatar');

    $commit = Commit::factory()->create();
    $commit->delete();

    $this->get(route('commit-open-graph-image.show', [$commit->user, $commit]))->assertNotFound();
});
