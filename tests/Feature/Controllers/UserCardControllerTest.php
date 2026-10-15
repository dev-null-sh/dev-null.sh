<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('returns a summary of the user for the hover card', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create(['username' => 'octocat', 'name' => 'The Octocat', 'bio' => 'Hello']);

    $user->followers()->attach($viewer);
    $user->following()->attach([$viewer->id, User::factory()->create()->id]);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->pending()->create();

    $response = $this->actingAs($viewer)
        ->getJson('/@octocat/card');

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', 'The Octocat')
        ->assertJsonPath('user.bio', 'Hello')
        ->assertJsonPath('user.commits_count', 2)
        ->assertJsonPath('user.followers_count', 1)
        ->assertJsonPath('user.following_count', 2)
        ->assertJsonPath('isFollowing', true)
        ->assertJsonPath('followsYou', true)
        ->assertJsonMissingPath('user.email')
        ->assertJsonMissingPath('user.github_id');
});

it('reports when neither user follows the other', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/@octocat/card');

    $response->assertOk()
        ->assertJsonPath('isFollowing', false)
        ->assertJsonPath('followsYou', false);
});

it('returns not found for unknown usernames', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/@missing/card');

    $response->assertNotFound();
});

it('requires authentication to view a hover card', function (): void {
    User::factory()->create(['username' => 'octocat']);

    $response = $this->getJson('/@octocat/card');

    $response->assertUnauthorized();
});
