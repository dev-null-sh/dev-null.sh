<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;

it('lists the ancestors of a commit from the root down', function (): void {
    $root = Commit::factory()->create();
    $middle = Commit::factory()->for($root, 'parent')->create();
    $reply = Commit::factory()->for($middle, 'parent')->create();

    $ancestors = new CommitAncestorsQuery()->get($reply, User::factory()->create());

    expect($ancestors->pluck('id')->all())->toBe([$root->id, $middle->id])
        ->and($ancestors->every(fn (Commit $ancestor): bool => $ancestor->relationLoaded('user')))->toBeTrue();
});

it('lists no ancestors for a top-level commit', function (): void {
    $ancestors = new CommitAncestorsQuery()->get(Commit::factory()->create(), User::factory()->create());

    expect($ancestors)->toBeEmpty();
});

it('stops at the first ancestor that is not visible', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $hidden = Commit::factory()->for($root, 'parent')->pending()->create();
    $middle = Commit::factory()->for($hidden, 'parent')->create();
    $reply = Commit::factory()->for($middle, 'parent')->create();

    $ancestors = new CommitAncestorsQuery()->get($reply, $viewer);

    expect($ancestors->pluck('id')->all())->toBe([$middle->id])
        ->and($ancestors->first()->relationLoaded('parent'))->toBeFalse();
});

it('keeps deleted ancestors as tombstones and continues past them', function (): void {
    $root = Commit::factory()->create();
    $deleted = Commit::factory()->for($root, 'parent')->create();
    $reply = Commit::factory()->for($deleted, 'parent')->create();
    $deleted->delete();

    $ancestors = new CommitAncestorsQuery()->get($reply, User::factory()->create());

    expect($ancestors->pluck('id')->all())->toBe([$root->id, $deleted->id])
        ->and($ancestors->last()->toArray())->toBe([
            'id' => $deleted->id,
            'parent_id' => $root->id,
            'deleted_at' => $deleted->deleted_at?->toJSON(),
        ]);
});

it('includes own pending ancestors', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->for($viewer)->pending()->create();
    $reply = Commit::factory()->for($root, 'parent')->for($viewer)->pending()->create();

    $ancestors = new CommitAncestorsQuery()->get($reply, $viewer);

    expect($ancestors->pluck('id')->all())->toBe([$root->id]);
});

it('limits the ancestors and points the oldest one at its parent', function (): void {
    $commits = [Commit::factory()->create()];

    foreach (range(1, 4) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->create();
    }

    $ancestors = new CommitAncestorsQuery()->get(end($commits), User::factory()->create(), limit: 2);

    expect($ancestors->pluck('id')->all())->toBe([$commits[2]->id, $commits[3]->id])
        ->and($ancestors->first()->parent->id)->toBe($commits[1]->id)
        ->and($ancestors->first()->parent->relationLoaded('user'))->toBeTrue();
});
