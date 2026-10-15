<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('renders own commits and commits of followed users newest first', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    $user->following()->attach($followed);

    Commit::factory()->for($user)->create(['title' => 'Mine', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($followed)->create(['title' => 'Followed', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($stranger)->create(['title' => 'Stranger']);

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'following')
            ->has('commits.data', 2)
            ->where('commits.data.0.title', 'Followed')
            ->where('commits.data.0.user.id', $followed->id)
            ->where('commits.data.0.user.is_following', true)
            ->missing('commits.data.0.user.email')
            ->where('commits.data.1.title', 'Mine')
            ->where('commits.data.1.user.is_following', false));
});

it('renders commits of everyone newest first', function (): void {
    $user = User::factory()->create();

    Commit::factory()->create(['title' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->create(['title' => 'Newer']);

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'everyone')
            ->has('commits.data', 2)
            ->where('commits.data.0.title', 'Newer')
            ->where('commits.data.1.title', 'Older'));
});

it('shares whether the viewer follows each author', function (): void {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    $viewer->following()->attach($followed);
    $followed->following()->attach(User::factory()->create());

    Commit::factory()->for($followed)->create(['created_at' => now()]);
    Commit::factory()->for($stranger)->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($viewer)->create(['created_at' => now()->subMinutes(2)]);

    $response = $this->actingAs($viewer)
        ->get(route('log.show', Log::Everyone));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.user.id', $followed->id)
            ->where('commits.data.0.user.is_following', true)
            ->where('commits.data.0.user.followers_count', 1)
            ->where('commits.data.0.user.following_count', 1)
            ->where('commits.data.1.user.id', $stranger->id)
            ->where('commits.data.1.user.is_following', false)
            ->where('commits.data.1.user.followers_count', 0)
            ->where('commits.data.1.user.following_count', 0)
            ->where('commits.data.2.user.id', $viewer->id)
            ->where('commits.data.2.user.is_following', false)
            ->missing('commits.data.0.user.email'));
});

it('paginates the log', function (Log $log): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count(25)->sequence(
        fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->whereNot('commits.next_cursor', null));
})->with(Log::cases());

it('returns not found for unknown logs', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/log/unknown');

    $response->assertNotFound();
});

it('requires authentication', function (Log $log): void {
    $response = $this->get(route('log.show', $log));

    $response->assertRedirectToRoute('home');
})->with(Log::cases());

it('hides pending commits of others from the log', function (Log $log): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    Commit::factory()->for($user)->pending()->create(['title' => 'Mine']);
    Commit::factory()->for($followed)->pending()->create(['title' => 'Followed']);

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.title', 'Mine')
            ->where('commits.data.0.status', 'pending'));
})->with(Log::cases());
