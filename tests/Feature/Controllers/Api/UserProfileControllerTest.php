<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

beforeEach(function (): void {
    $this->user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle', 'bio' => 'Old bio']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('syncs the profile from github, and answers like me', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with('583231')->andReturn([
        'id' => 583231,
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        'bio' => 'New bio',
        'company' => '@github',
        'location' => 'San Francisco',
        'blog' => 'https://github.blog',
    ]);
    $github->shouldReceive('login')->with('583231')->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-profile.update'));

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.handle', 'octocat')
        ->assertJsonPath('data.name', 'The Octocat')
        ->assertJsonPath('data.bio', 'New bio')
        ->assertJsonPath('data.company', '@github')
        ->assertJsonPath('data.location', 'San Francisco')
        ->assertJsonPath('data.website', 'https://github.blog')
        ->assertJsonPath('data.github_url', 'https://github.com/octocat')
        ->assertJsonPath('data.github_synced_at', now()->toIso8601ZuluString())
        ->assertJsonPath('data.commits_count', 0)
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh())
        ->handle->toBe('octocat')
        ->bio->toBe('New bio')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

it('answers with a code when github could not be synced, as the web words it', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('account')->with($this->user->github_id)->andThrow(new RequestException(new Response(new Psr7Response(500))));

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-profile.update'));

    $response->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'Could not sync from GitHub. Try again in a minute.',
            'code' => 'github_sync_failed',
        ]);

    expect($this->user->refresh())
        ->handle->toBe('old-handle')
        ->github_synced_at->toBeNull();
});

it('allows one sync a minute, shared with the web, with a code', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with('583231')->andReturn([
        'id' => 583231,
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        'bio' => 'I am the Octocat.',
        'company' => '@github',
        'location' => 'San Francisco',
        'blog' => 'https://github.blog',
    ]);
    $github->shouldReceive('login')->with('583231')->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $this->actingAs($this->user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'))
        ->assertInertiaFlash('toast.type', 'success');

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-profile.update'));

    $response->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertExactJson([
            'message' => 'You just synced. Try again in a minute.',
            'code' => 'rate_limited',
        ]);

    $github->shouldHaveReceived('account')->once();

    $this->travel(1)->minute();

    signedApiRequest($this->keyPair, 'PUT', route('api-user-profile.update'))->assertOk();
});

it('rejects requests that are not signed', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('account');

    $this->putJson(route('api-user-profile.update'))->assertUnauthorized();
});
