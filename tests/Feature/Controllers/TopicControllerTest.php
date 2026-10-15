<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Topic;
use App\Models\User;

it('renders the commits of a topic newest first', function (): void {
    $topic = Topic::factory()->create(['name' => 'laravel']);

    Commit::factory()->count(Topic::MIN_COMMITS - 1)->hasAttached($topic)->create(['created_at' => now()->subHour()]);
    Commit::factory()->hasAttached($topic)->create(['title' => 'Newest']);
    Commit::factory()->create(['title' => 'Unrelated']);

    $response = $this->actingAs(User::factory()->create())
        ->get('/topics/laravel');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('topic/show')
            ->where('topic.name', 'laravel')
            ->where('topic.commits_count', Topic::MIN_COMMITS)
            ->has('commits.data', Topic::MIN_COMMITS)
            ->where('commits.data.0.title', 'Newest')
            ->where('commits.data.0.promoted_topics.0.name', 'laravel')
            ->missing('commits.data.0.user.email'));
});

it('hides topics that are not promoted yet', function (): void {
    $topic = Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS - 1))->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('topic.show', $topic));

    $response->assertNotFound();
});

it('only shows the viewer commits they may see', function (): void {
    $viewer = User::factory()->create();
    $topic = Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS))->create();

    Commit::factory()->pending()->hasAttached($topic)->create(['title' => 'Someone else pending']);
    Commit::factory()->for($viewer)->pending()->hasAttached($topic)->create(['title' => 'My pending']);

    $response = $this->actingAs($viewer)
        ->get(route('topic.show', $topic));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('topic.commits_count', Topic::MIN_COMMITS)
            ->has('commits.data', Topic::MIN_COMMITS + 1)
            ->where('commits.data.0.title', 'My pending'));
});

it('hides topics whose commits are not approved yet', function (): void {
    $topic = Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS)->pending())->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('topic.show', $topic));

    $response->assertNotFound();
});

it('returns not found for unknown topics', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/topics/missing');

    $response->assertNotFound();
});

it('requires authentication to view a topic', function (): void {
    $topic = Topic::factory()->create();

    $response = $this->get(route('topic.show', $topic));

    $response->assertRedirectToRoute('home');
});
