<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\User;

it('may follow a tag', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('tag.show', $tag)
        ->post(route('tag-follow.store', $tag));

    $response->assertRedirectToRoute('tag.show', $tag);

    expect($user->isFollowingTag($tag))->toBeTrue();
});

it('may unfollow a tag', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->followedTags()->attach($tag);

    $response = $this->actingAs($user)
        ->fromRoute('tag.show', $tag)
        ->delete(route('tag-follow.destroy', $tag));

    $response->assertRedirectToRoute('tag.show', $tag);

    expect($user->isFollowingTag($tag))->toBeFalse();
});

it('requires authentication to follow', function (): void {
    $tag = Tag::factory()->create();

    $response = $this->post(route('tag-follow.store', $tag));

    $response->assertRedirectToRoute('home');

    expect($tag->followers()->count())->toBe(0);
});
