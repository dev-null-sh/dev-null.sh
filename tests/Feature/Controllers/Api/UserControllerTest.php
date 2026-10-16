<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('shows a public profile with public counts', function (): void {
    $user = User::factory()->create([
        'username' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'company' => '@github',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
    ]);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->for($user)->create()->delete();

    $user->followers()->attach(User::factory()->count(3)->create());
    $user->following()->attach(User::factory()->create());

    $response = $this->getJson(route('api-user.show', $user));

    $response->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'username' => 'octocat',
            'name' => 'The Octocat',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
            'bio' => 'I am the Octocat.',
            'company' => '@github',
            'location' => 'San Francisco',
            'website' => 'https://github.blog',
            'github_url' => 'https://github.com/octocat',
            'html_url' => route('user.show', $user),
            'feed_url' => route('user-feed.show', $user),
            'created_at' => now()->toIso8601ZuluString(),
            'commits_count' => 2,
            'followers_count' => 3,
            'following_count' => 1,
        ]]);
});

it('never exposes hidden fields', function (): void {
    $user = User::factory()->create([
        'username' => 'octocat',
        'email' => 'octocat@github.com',
        'github_id' => '583231',
        'preferred_lane' => 3,
    ]);

    $this->getJson('/api/v1/users/octocat')
        ->assertOk()
        ->assertDontSee('octocat@github.com')
        ->assertDontSee('583231')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.github_id')
        ->assertJsonMissingPath('data.preferred_lane')
        ->assertJsonMissingPath('data.lane')
        ->assertJsonMissingPath('data.remember_token');
});

it('returns not found for unknown usernames', function (): void {
    $this->getJson('/api/v1/users/missing')->assertNotFound();
});
