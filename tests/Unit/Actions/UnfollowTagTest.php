<?php

declare(strict_types=1);

use App\Actions\UnfollowTag;
use App\Models\Tag;
use App\Models\User;

it('may unfollow a tag', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->followedTags()->attach($tag);

    $action = resolve(UnfollowTag::class);

    $action->handle($user, $tag);

    expect($user->isFollowingTag($tag))->toBeFalse();
});

it('does nothing when not following', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $action = resolve(UnfollowTag::class);

    $action->handle($user, $tag);

    expect($user->followedTags()->count())->toBe(0);
});
