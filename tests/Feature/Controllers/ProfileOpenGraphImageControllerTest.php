<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Profile;
use App\Models\User;

it('renders a link preview image of a profile for guests', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->once()->with('https://avatars.githubusercontent.com/u/583231')->andReturn(fakeAvatar());

    $user = User::factory()->create(['avatar_url' => 'https://avatars.githubusercontent.com/u/583231']);

    $response = $this->get(route('user-open-graph-image.show', $user));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600')
        ->and(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('falls back to an initial when the avatar cannot be downloaded', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->once()->andReturnNull();

    $user = User::factory()->create();

    $response = $this->get(route('user-open-graph-image.show', $user));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('falls back to an initial when the avatar is not an image', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->once()->andReturn('<html>not an image</html>');

    $response = $this->get(route('user-open-graph-image.show', User::factory()->create()));

    $response->assertOk();

    expect(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('renders a profile without an avatar or bio without requesting anything', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('avatar');
    $user = User::factory()->withBlankProfile()->create();

    $this->get(route('user-open-graph-image.show', $user))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('reuses the rendered image until the profile changes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());

    $user = User::factory()->create();

    $this->get(route('user-open-graph-image.show', $user))->assertOk();
    $this->get(route('user-open-graph-image.show', $user))->assertOk();

    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());
    $this->travel(1)->minute();
    $user->profile->update(['bio' => 'Updated on GitHub.']);

    $this->get(route('user-open-graph-image.show', $user))->assertOk();
});

it('renders a link preview image of an organization for guests', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('avatar')->once()->with('https://avatars.githubusercontent.com/u/958072')->andReturn(fakeAvatar());

    Profile::factory()->organization()->create(['handle' => 'laravel', 'avatar_url' => 'https://avatars.githubusercontent.com/u/958072']);

    $response = $this->get('/@laravel/og.png');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600')
        ->and(array_slice((array) getimagesizefromstring((string) $response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('renders an organization without an avatar or bio without requesting anything', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('avatar');
    Profile::factory()->organization()->create(['handle' => 'laravel', 'avatar_url' => null, 'bio' => null]);

    $this->get('/@laravel/og.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('reuses the rendered image of an organization until it changes', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());

    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);

    $this->get('/@laravel/og.png')->assertOk();
    $this->get('/@laravel/og.png')->assertOk();

    $github->shouldReceive('avatar')->once()->andReturn(fakeAvatar());
    $organization->update(['bio' => 'Updated on GitHub.']);

    $this->get('/@laravel/og.png')->assertOk();
});

it('returns not found for unknown handles', function (): void {
    $this->get('/@missing/og.png')->assertNotFound();
});

it('renders the link preview image of a profile whatever the case of the handle', function (): void {
    Profile::factory()->organization()->create(['handle' => 'Laravel']);

    $this->get('/@laravel/og.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
