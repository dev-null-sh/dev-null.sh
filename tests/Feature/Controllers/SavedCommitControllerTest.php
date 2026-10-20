<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('shows only the viewers stars in saved, newest first', function (): void {
    $viewer = User::factory()->create();
    $first = Commit::factory()->create();
    $last = Commit::factory()->create();
    $other = Commit::factory()->create();
    $viewer->starredCommits()->attach($first, ['created_at' => now()->subMinute()]);
    $viewer->starredCommits()->attach($last, ['created_at' => now()]);
    User::factory()->create()->starredCommits()->attach($other);

    $this->actingAs($viewer)->get(route('saved.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('saved/index')
        ->has('commits.data', 2)
        ->where('commits.data.0.id', $last->id)
        ->where('commits.data.1.id', $first->id));
});

it('keeps saved private', function (): void {
    $this->get('/saved')->assertRedirectToRoute('home');
});

it('paginates stars with the same timestamp without repeating commits', function (): void {
    $viewer = User::factory()->create();
    $commits = Commit::factory()->count(21)->create();
    $viewer->starredCommits()->attach($commits, ['created_at' => '2026-10-20 10:00:00']);

    $first = $this->actingAs($viewer)->get(route('saved.index'))->assertOk();
    $cursor = $first->inertiaProps('commits.next_cursor');

    $first->assertInertia(fn ($page) => $page->has('commits.data', 20));
    $this->get(route('saved.index', ['cursor' => $cursor]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $commits->first()->id));
});
