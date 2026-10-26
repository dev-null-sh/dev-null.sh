<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle', 'bio' => 'Old bio']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('syncs the profile from github, and answers like me', function (): void {
    fakeGitHubAccount($this->user, ['login' => 'octocat', 'bio' => 'New bio']);

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
    Http::fake([
        "api.github.com/user/{$this->user->github_id}" => Http::response(['message' => 'Server Error'], 500),
    ]);

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
    fakeGitHubAccount($this->user);

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

    Http::assertSentCount(4);

    $this->travel(1)->minute();

    signedApiRequest($this->keyPair, 'PUT', route('api-user-profile.update'))->assertOk();
});

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-profile.update'))->assertUnauthorized();

    Http::assertNothingSent();
});
