<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

it('lists who starred a commit, newest first', function (): void {
    $commit = Commit::factory()->create();
    [$earlier, $later] = User::factory()->count(2)->create();
    $this->travelTo(now()->subMinute(), fn () => $commit->stars()->attach($earlier));
    $commit->stars()->attach($later);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-stargazers.index', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/stargazers')
            ->where('commit.id', $commit->id)
            ->where('commit.stars_count', 2)
            ->has('users.data', 2)
            ->where('users.data.0.id', $later->id)
            ->where('users.data.1.id', $earlier->id)
            ->missing('users.data.0.email'));
});

it('marks the stargazers the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $followed = User::factory()->create();
    $commit->stars()->attach([User::factory()->create()->id, $followed->id]);
    $viewer->following()->attach($followed);

    $response = $this->actingAs($viewer)
        ->get(route('commit-stargazers.index', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users): bool => collect($users)->firstWhere('id', $followed->id)['is_followed'] === true
                && collect($users)->where('is_followed', false)->count() === 1));
});

it('looks missing when the viewer cannot see the commit', function (): void {
    $commit = Commit::factory()->create(['status' => CommitStatus::Pending]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-stargazers.index', [$commit->user, $commit]));

    $response->assertNotFound();
});

it('looks missing under another user', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-stargazers.index', [User::factory()->create(), $commit]));

    $response->assertNotFound();
});

it('sends guests to sign in', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->get(route('commit-stargazers.index', [$commit->user, $commit]));

    $response->assertRedirect();
});
