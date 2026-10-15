<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('renders a user profile', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $viewer = User::factory()->create();

    $user->followers()->attach($viewer);
    $user->following()->attach(User::factory()->count(2)->create());

    Commit::factory()->for($user)->create(['created_at' => now()->subDay(), 'title' => 'Older']);
    Commit::factory()->for($user)->create(['title' => 'Newer']);
    Commit::factory()->create();

    $response = $this->actingAs($viewer)
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('user.id', $user->id)
            ->where('user.username', 'octocat')
            ->where('user.commits_count', 2)
            ->where('user.followers_count', 1)
            ->where('user.following_count', 2)
            ->missing('user.email')
            ->missing('user.github_id')
            ->where('isFollowing', true)
            ->has('commits.data', 2)
            ->where('commits.data.0.title', 'Newer')
            ->where('commits.data.0.user.username', 'octocat')
            ->where('commits.data.0.user.is_following', true)
            ->where('commits.data.0.user.followers_count', 1)
            ->where('commits.data.0.user.following_count', 2)
            ->where('commits.data.1.title', 'Older'));
});

it('renders the user commit activity for the last year', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subYears(2)]);
    Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activity', 369)
            ->where('activity.0', ['date' => '2025-10-12', 'count' => 0])
            ->where('activity.365', ['date' => '2026-10-12', 'count' => 1])
            ->where('activity.368', ['date' => '2026-10-15', 'count' => 2]));
});

it('renders whether the viewer is not following the user', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user.show', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isFollowing', false)
            ->has('commits.data', 0));
});

it('returns not found for unknown usernames', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/@missing');

    $response->assertNotFound();
});

it('requires authentication to view a profile', function (): void {
    $user = User::factory()->create();

    $response = $this->get(route('user.show', $user));

    $response->assertRedirectToRoute('home');
});

it('may delete user account', function (): void {
    $user = User::factory()->create();
    Commit::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'));

    $response->assertRedirectToRoute('home');

    expect($user->fresh())->toBeNull()
        ->and(Commit::query()->count())->toBe(0);

    $this->assertGuest();
});

it('hides pending and rejected commits from other users', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->create(['title' => 'Approved']);
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 1)
            ->where('activity.368.count', 1)
            ->has('commits.data', 1)
            ->where('commits.data.0.title', 'Approved'));
});

it('shows own pending and rejected commits', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $response = $this->actingAs($user)
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 3)
            ->where('activity.368.count', 3)
            ->has('commits.data', 3));
});
