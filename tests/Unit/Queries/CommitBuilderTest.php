<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('scopes commits to those visible to a user', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create();
    $ownPending = Commit::factory()->for($user)->pending()->create();
    $ownRejected = Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();

    expect(Commit::query()->visibleTo($user)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$approved->id, $ownPending->id, $ownRejected->id])->sort()->values()->all());
});

it('loads the star count and whether the viewer starred', function (): void {
    $viewer = User::factory()->create();
    $starred = Commit::factory()->create();
    $plain = Commit::factory()->create();

    $viewer->starredCommits()->attach($starred);
    User::factory()->create()->starredCommits()->attach($starred);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $commits = Commit::query()->withStarsFor($viewer)->with('user')->get()->keyBy('id');

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($commits[$starred->id]->stars_count)->toBe(2)
        ->and($commits[$starred->id]->starred)->toBeTrue()
        ->and($commits[$plain->id]->stars_count)->toBe(0)
        ->and($commits[$plain->id]->starred)->toBeFalse();
});

it('scopes commits to pending ones', function (): void {
    $pending = Commit::factory()->pending()->create();
    Commit::factory()->create();
    Commit::factory()->rejected()->create();

    expect(Commit::query()->pending()->sole()->is($pending))->toBeTrue();
});

it('scopes commits to top-level ones', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create();

    expect(Commit::query()->topLevel()->pluck('id')->all())->toBe([$parent->id]);
});

it('scopes thread commits to visible ones and deleted ones that still have visible replies', function (): void {
    $user = User::factory()->create();

    $commit = Commit::factory()->create();
    $tombstone = Commit::factory()->create();
    Commit::factory()->for($tombstone, 'parent')->create();
    $tombstone->delete();
    $deletedChain = Commit::factory()->create();
    $deletedReply = Commit::factory()->for($deletedChain, 'parent')->create();
    Commit::factory()->for($deletedReply, 'parent')->create();
    $deletedReply->delete();
    $deletedChain->delete();
    $onlyHiddenReplies = Commit::factory()->create();
    Commit::factory()->for($onlyHiddenReplies, 'parent')->pending()->create();
    $onlyHiddenReplies->delete();
    Commit::factory()->create()->delete();
    Commit::factory()->pending()->create();

    $ids = Commit::query()->topLevel()->visibleInThreadTo($user)->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$commit->id, $tombstone->id, $deletedChain->id])->sort()->values()->all());
});

it('scopes commits to approved ones', function (): void {
    $user = User::factory()->create();
    $approved = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    expect(Commit::query()->approved()->sole()->is($approved))->toBeTrue();
});
