<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

it('renders the posts of a topic newest first', function (): void {
    $topic = Topic::factory()->create(['name' => 'laravel']);

    Post::factory()->hasAttached($topic)->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    Post::factory()->hasAttached($topic)->create(['body' => 'Newer']);
    Post::factory()->create(['body' => 'Unrelated']);

    $response = $this->actingAs(User::factory()->create())
        ->get('/topics/laravel');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('topic/show')
            ->where('topic.name', 'laravel')
            ->where('topic.posts_count', 2)
            ->has('posts.data', 2)
            ->where('posts.data.0.body', 'Newer')
            ->missing('posts.data.0.user.email')
            ->where('posts.data.1.body', 'Older'));
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
