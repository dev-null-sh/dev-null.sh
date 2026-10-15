<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

test('to array', function (): void {
    $post = Post::factory()->create()->refresh();

    expect(array_keys($post->toArray()))
        ->toBe([
            'id',
            'user_id',
            'body',
            'created_at',
            'updated_at',
        ]);
});

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    expect($post->user->is($user))->toBeTrue();
});

it('belongs to many topics', function (): void {
    $topic = Topic::factory()->create();
    $post = Post::factory()->hasAttached($topic)->create();

    expect($post->topics->sole()->is($topic))->toBeTrue();
});
