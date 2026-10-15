<?php

declare(strict_types=1);

use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;
use App\Queries\TagLogQuery;

it('includes commits and replies with the tag newest first', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();
    Commit::factory()->create();
    Commit::factory()->hasAttached(Tag::factory())->create();

    $tag->commits()->attach([$older->id, $reply->id]);

    $commits = resolve(TagLogQuery::class)->builder($user, $tag)->get();

    expect($commits->modelKeys())->toBe([$reply->id, $older->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->relationLoaded('linkPreview'))->toBeTrue()
        ->and($commits->first()->relationLoaded('images'))->toBeTrue()
        ->and($commits->first()->parent->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->replies_count)->toBe(0)
        ->and($commits->first()->stars_count)->toBe(0);
});

it('only includes commits visible to the user', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $approved = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $ownPending = Commit::factory()->for($user)->pending()->create();
    $pending = Commit::factory()->pending()->create();
    $rejected = Commit::factory()->rejected()->create();
    $deleted = Commit::factory()->create();
    $deleted->delete();

    $tag->commits()->attach([$approved->id, $ownPending->id, $pending->id, $rejected->id, $deleted->id]);

    $commits = resolve(TagLogQuery::class)->builder($user, $tag)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});

it('hides the parent of a reply when the user cannot see it', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();
    $reply = Commit::factory()->for(Commit::factory()->pending(), 'parent')->create();
    $tag->commits()->attach($reply);

    $commit = resolve(TagLogQuery::class)->builder($user, $tag)->sole();

    expect($commit->parent)->toBeNull();
});

it('marks the commits the user saved', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();
    $saved = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $unsaved = Commit::factory()->create();
    $tag->commits()->attach([$saved->id, $unsaved->id]);
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $saved->id]);

    $commits = resolve(TagLogQuery::class)->builder($user, $tag)->get()->keyBy('id');

    expect($commits[$saved->id]->is_saved)->toBeTrue()
        ->and($commits[$unsaved->id]->is_saved)->toBeFalse();
});
