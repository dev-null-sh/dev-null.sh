<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;

test('to array', function (): void {
    $tag = Tag::factory()->create()->refresh();

    expect(array_keys($tag->toArray()))->toBe(['name']);
});

it('has commits', function (): void {
    $tag = Tag::factory()->create();
    $commits = Commit::factory()->count(2)->create();
    Commit::factory()->create();

    $tag->commits()->attach($commits);

    expect($tag->commits->modelKeys())->toEqualCanonicalizing($commits->modelKeys())
        ->and($commits->first()->tags->modelKeys())->toBe([$tag->id]);
});

it('has followers', function (): void {
    $tag = Tag::factory()->create();
    $user = User::factory()->create();
    User::factory()->create();

    $user->followedTags()->attach($tag);

    expect($tag->followers->modelKeys())->toBe([$user->id])
        ->and($user->followedTags->modelKeys())->toBe([$tag->id]);
});

it('forgets its commits when they are erased', function (): void {
    $tag = Tag::factory()->create();
    $commit = Commit::factory()->create();
    $tag->commits()->attach($commit);

    $commit->forceDelete();

    expect($tag->commits()->count())->toBe(0);
});

it('forgets its followers when they delete their account', function (): void {
    $tag = Tag::factory()->create();
    $user = User::factory()->create();
    $user->followedTags()->attach($tag);

    $user->delete();

    expect($tag->followers()->count())->toBe(0);
});
