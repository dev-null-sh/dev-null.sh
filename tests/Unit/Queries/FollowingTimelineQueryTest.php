<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use App\Queries\FollowingTimelineQuery;
use Illuminate\Support\Facades\DB;

it('includes own posts and posts of followed users newest first', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    $own = Post::factory()->for($user)->create(['created_at' => now()->subMinute()]);
    $followedPost = Post::factory()->for($followed)->create();
    Post::factory()->create();

    $query = resolve(FollowingTimelineQuery::class);

    $posts = $query->builder($user)->get();

    expect($posts->modelKeys())->toBe([$followedPost->id, $own->id])
        ->and($posts->first()->relationLoaded('user'))->toBeTrue();
});

it('orders posts by creation date rather than insertion order', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    $older = Post::factory()->for($followed)->create(['created_at' => now()->subDay()]);
    $newer = Post::factory()->for($user)->create(['created_at' => now()->subHour()]);
    $oldest = Post::factory()->for($followed)->create(['created_at' => now()->subWeek()]);

    $posts = resolve(FollowingTimelineQuery::class)->builder($user)->get();

    expect($posts->modelKeys())->toBe([$newer->id, $older->id, $oldest->id]);
});

it('resolves followed users in a subquery instead of loading their ids', function (): void {
    $user = User::factory()->create();

    $user->following()->attach(User::factory()->count(3)->create());

    DB::enableQueryLog();

    $builder = resolve(FollowingTimelineQuery::class)->builder($user);

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($builder->toSql())->toContain('in (select "users"."id" from "users" inner join "follows"');
});
