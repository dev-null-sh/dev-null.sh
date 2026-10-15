<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Topic;

test('to array', function (): void {
    $topic = Topic::factory()->create()->refresh();

    expect(array_keys($topic->toArray()))
        ->toBe([
            'id',
            'name',
            'created_at',
            'updated_at',
        ]);
});

it('belongs to many posts', function (): void {
    $post = Post::factory()->create();
    $topic = Topic::factory()->hasAttached($post)->create();

    expect($topic->posts->sole()->is($post))->toBeTrue();
});
