<?php

declare(strict_types=1);

use App\Actions\CreateTagFollow;
use App\Models\Tag;
use App\Models\User;

it('may follow a tag', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $action = resolve(CreateTagFollow::class);

    $action->handle($user, $tag);

    expect($user->isFollowingTag($tag))->toBeTrue();
});

it('is idempotent', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $action = resolve(CreateTagFollow::class);

    $action->handle($user, $tag);
    $action->handle($user, $tag);

    expect($user->followedTags()->count())->toBeOne();
});
