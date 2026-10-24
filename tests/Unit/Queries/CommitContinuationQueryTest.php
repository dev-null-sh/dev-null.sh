<?php

declare(strict_types=1);

use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;
use App\Services\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('follows the author replying to themselves down the thread', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $second = Commit::factory()->for($root, 'parent')->for($author)->create();
    Commit::factory()->for($root, 'parent')->create();
    $third = Commit::factory()->for($second, 'parent')->for($author)->create();
    Commit::factory()->for($second, 'parent')->create();
    Commit::factory()->for($third, 'parent')->create();

    $continuation = new CommitContinuationQuery()->get($root, User::factory()->create())->commits;

    expect($continuation->pluck('id')->all())->toBe([$second->id, $third->id])
        ->and($continuation->pluck('replies_count')->all())->toBe([1, 1])
        ->and($continuation->every(fn (Commit $commit): bool => $commit->relationLoaded('user')))->toBeTrue();
});

it('continues from a commit in the middle of the thread', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $second = Commit::factory()->for($root, 'parent')->for($author)->create();
    $third = Commit::factory()->for($second, 'parent')->for($author)->create();

    $continuation = new CommitContinuationQuery()->get($second, User::factory()->create())->commits;

    expect($continuation->pluck('id')->all())->toBe([$third->id]);
});

it('takes the earliest reply of the author at each step', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $later = Commit::factory()->for($root, 'parent')->for($author)->create(['created_at' => now()]);
    $earlier = Commit::factory()->for($root, 'parent')->for($author)->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($later, 'parent')->for($author)->create();

    $continuation = new CommitContinuationQuery()->get($root, User::factory()->create())->commits;

    expect($continuation->pluck('id')->all())->toBe([$earlier->id]);
});

it('stops where someone else replies in between', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $other = Commit::factory()->for($root, 'parent')->create();
    Commit::factory()->for($other, 'parent')->for($author)->create();

    $continuation = new CommitContinuationQuery()->get($root, User::factory()->create())->commits;

    expect($continuation)->toBeEmpty();
});

it('stops at a reply the viewer may not see', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $pending = Commit::factory()->for($root, 'parent')->for($author)->pending()->create();
    Commit::factory()->for($pending, 'parent')->for($author)->create();

    expect(new CommitContinuationQuery()->get($root, User::factory()->create())->commits)->toBeEmpty()
        ->and(new CommitContinuationQuery()->get($root, $author)->commits)->toHaveCount(2);
});

it('ends at a reply the viewer may not see rather than skipping to a later one', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    Commit::factory()->for($root, 'parent')->for($author)->pending()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($root, 'parent')->for($author)->create();

    expect(new CommitContinuationQuery()->get($root, $viewer)->commits)->toBeEmpty()
        ->and(Commit::query()->withVisibleReplyCount($viewer)->withContinuationId($viewer)->findOrFail($root->id)->continuation_id)->toBeNull();
});

it('stops at a deleted reply', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $deleted = Commit::factory()->for($root, 'parent')->for($author)->create();
    Commit::factory()->for($deleted, 'parent')->for($author)->create();
    $deleted->delete();

    expect(new CommitContinuationQuery()->get($root, User::factory()->create())->commits)->toBeEmpty();
});

it('shows the first commits of a long thread with its length and the next commit to load', function (): void {
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, CommitContinuationQuery::PER_PAGE + 2) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->for($author)->create();
    }

    $continuation = new CommitContinuationQuery()->get($commits[0], User::factory()->create());

    expect($continuation->commits)->toHaveCount(CommitContinuationQuery::PER_PAGE)
        ->and($continuation->commits->last()->id)->toBe($commits[CommitContinuationQuery::PER_PAGE]->id)
        ->and($continuation->commits->last()->replies_count)->toBe(0)
        ->and($continuation->length)->toBe(CommitContinuationQuery::PER_PAGE + 2)
        ->and($continuation->nextId)->toBe($commits[CommitContinuationQuery::PER_PAGE + 1]->id);
});

it('knows the place of the commit in the thread', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $second = Commit::factory()->for($root, 'parent')->for($author)->create(['created_at' => now()->subMinute()]);
    $later = Commit::factory()->for($root, 'parent')->for($author)->create();
    $third = Commit::factory()->for($second, 'parent')->for($author)->create();
    $viewer = User::factory()->create();

    expect(new CommitContinuationQuery()->get($root, $viewer)->position)->toBe(1)
        ->and(new CommitContinuationQuery()->get($third, $viewer)->position)->toBe(3)
        ->and(new CommitContinuationQuery()->get($later, $viewer)->position)->toBe(1);
});

it('caps the length of a very long thread', function (): void {
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, CommitContinuationQuery::MAX_LENGTH + 1) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->for($author)->create();
    }

    $viewer = User::factory()->create();
    $capped = new CommitContinuationQuery()->get($commits[0], $viewer);
    $complete = new CommitContinuationQuery()->get($commits[1], $viewer);

    expect($capped->length)->toBe(CommitContinuationQuery::MAX_LENGTH)
        ->and($capped->isCapped)->toBeTrue()
        ->and($capped->nextId)->not->toBeNull()
        ->and($complete->length)->toBe(CommitContinuationQuery::MAX_LENGTH)
        ->and($complete->isCapped)->toBeFalse();
});

it('has no continuation for a muted author', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    Commit::factory()->for($root, 'parent')->for($root->user)->create();
    $viewer->muting()->attach($root->user);

    expect(new CommitContinuationQuery()->get($root, $viewer)->commits)->toBeEmpty();
});

it('follows the thread in the same number of queries however long it is', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    resolve(Features::class)->all();
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, 6) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->for($author)->create();
    }

    $count = function (Commit $commit): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        new CommitContinuationQuery()->get($commit, User::factory()->create());

        return count(DB::getQueryLog());
    };

    expect($count($commits[5]))->toBe($count($commits[1]));
});

it('has no continuation for a commit without an author', function (): void {
    $root = Commit::factory()->create(['user_id' => null]);
    Commit::factory()->for($root, 'parent')->create(['user_id' => null]);

    expect(new CommitContinuationQuery()->get($root, User::factory()->create())->commits)->toBeEmpty();
});
