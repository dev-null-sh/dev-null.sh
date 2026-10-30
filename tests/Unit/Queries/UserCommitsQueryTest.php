<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\UserCommitsQuery;

it('includes the top-level commits of the user newest first', function (): void {
    $user = User::factory()->create();

    $older = Commit::factory()->for($user)->create(['created_at' => now()->subMinute()]);
    $newer = Commit::factory()->for($user)->create();
    Commit::factory()->for($older, 'parent')->create();
    Commit::factory()->for($user)->for(Commit::factory(), 'parent')->create();
    Commit::factory()->create();

    $commits = resolve(UserCommitsQuery::class)->builder($user, User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$newer->id, $older->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->last()->replies_count)->toBe(1);
});

it('only includes the commits the viewer may see', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->for($user)->create(['created_at' => now()->subMinute()]);
    $pending = Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    expect(resolve(UserCommitsQuery::class)->builder($user, User::factory()->create())->get()->modelKeys())->toBe([$approved->id])
        ->and(resolve(UserCommitsQuery::class)->builder($user, $user)->get()->modelKeys())->toHaveCount(3)->toContain($pending->id)
        ->and(resolve(UserCommitsQuery::class)->builder($user, null)->get()->modelKeys())->toBe([$approved->id]);
});

it('lists only the visible shipped commits, including a pinned one', function (): void {
    $user = User::factory()->create();
    $viewer = User::factory()->create();
    $shipped = Commit::factory()->for($user)->create(['shipped' => true, 'created_at' => now()->subMinute()]);
    $pending = Commit::factory()->for($user)->pending()->create(['shipped' => true, 'created_at' => now()]);
    $pinned = Commit::factory()->for($user)->create(['shipped' => true, 'created_at' => now()->subMinutes(2)]);
    $user->update(['pinned_commit_id' => $pinned->id]);
    $rejected = Commit::factory()->for($user)->rejected()->create(['shipped' => true, 'created_at' => now()->subMinutes(3)]);
    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->for($shipped, 'parent')->create(['shipped' => true]);
    Commit::factory()->create(['shipped' => true]);

    $query = resolve(UserCommitsQuery::class);

    expect($query->builder($user, $viewer, shipped: true)->get()->modelKeys())->toBe([$shipped->id, $pinned->id])
        ->and($query->builder($user, null, shipped: true)->get()->modelKeys())->toBe([$shipped->id, $pinned->id])
        ->and($query->builder($user, $user, shipped: true)->get()->modelKeys())->toBe([
            $pending->id,
            $shipped->id,
            $pinned->id,
            $rejected->id,
        ])
        ->and($query->builder($user, $user)->get()->modelKeys())->not->toContain($pinned->id);
});

it('leaves the pinned commit out of the commits tab', function (): void {
    $user = User::factory()->create();
    $pinned = Commit::factory()->for($user)->create();
    $other = Commit::factory()->for($user)->create();
    $user->update(['pinned_commit_id' => $pinned->id]);

    expect(resolve(UserCommitsQuery::class)->builder($user, $user)->get()->modelKeys())->toBe([$other->id]);
});

it('includes the replies of the user with the parents the viewer may see', function (): void {
    $user = User::factory()->create();
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();
    $hiddenParent = Commit::factory()->pending()->create();
    $reply = Commit::factory()->for($user)->for($parent, 'parent')->create(['created_at' => now()->subMinute()]);
    $replyToHidden = Commit::factory()->for($user)->for($hiddenParent, 'parent')->create();
    Commit::factory()->for($user)->create();

    $replies = resolve(UserCommitsQuery::class)->builder($user, $viewer, replies: true)->get();

    expect($replies->modelKeys())->toBe([$replyToHidden->id, $reply->id])
        ->and($replies->first()->parent)->toBeNull()
        ->and($replies->last()->parent?->id)->toBe($parent->id)
        ->and($replies->last()->parent?->relationLoaded('user'))->toBeTrue();
});
