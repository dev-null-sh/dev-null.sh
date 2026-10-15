<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('renders a user profile', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $viewer = User::factory()->create();

    $user->followers()->attach($viewer);
    $user->following()->attach(User::factory()->count(2)->create());

    Commit::factory()->for($user)->create(['created_at' => now()->subDay(), 'body' => 'Older']);
    Commit::factory()->for($user)->create(['body' => 'Newer']);
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
            ->where('commits.data.0.body', 'Newer')
            ->where('commits.data.0.user.username', 'octocat')
            ->where('commits.data.1.body', 'Older'));
});

it('does not count the user relations or activity when reloading only commits', function (): void {
    $user = User::factory()->has(Commit::factory())->create(['username' => 'octocat']);

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(function ($page): void {
            $page->where('user.commits_count', 1);

            DB::enableQueryLog();

            $page->reloadOnly('commits', fn ($reload) => $reload
                ->missing('user')
                ->missing('isFollowing')
                ->missing('activity'));
        });

    expect(array_column(DB::getQueryLog(), 'query'))
        ->not->toBeEmpty()
        ->each(fn ($query) => $query->not->toContain('count(*)')->not->toContain('exists'));
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

    Commit::factory()->for($user)->create(['body' => 'Approved']);
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 1)
            ->where('activity.368.count', 1)
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Approved'));
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

it('lists who a user is following', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach(User::factory()->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-following.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'following')
            ->where('user.username', 'octocat')
            ->has('users.data', 1)
            ->where('users.data.0.id', $followed->id));
});

it('lists a user\'s followers', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $follower = User::factory()->create();
    $user->followers()->attach($follower);
    $user->following()->attach(User::factory()->create());

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-followers.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'followers')
            ->has('users.data', 1)
            ->where('users.data.0.id', $follower->id)
            ->missing('users.data.0.email'));
});

it('requires authentication to list follows', function (): void {
    $user = User::factory()->create();

    $this->get(route('user-following.index', $user))->assertRedirectToRoute('home');
    $this->get(route('user-followers.index', $user))->assertRedirectToRoute('home');
});
