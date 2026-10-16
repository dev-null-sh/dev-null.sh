<?php

declare(strict_types=1);

use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Services\TheAlgorithm;

it('may approve many commits', function (): void {
    $commits = Commit::factory()->pending()->count(2)->create();
    $other = Commit::factory()->pending()->create();

    $action = resolve(UpdateCommitStatuses::class);

    $action->handle($commits->modelKeys(), CommitStatus::Approved);

    foreach ($commits as $commit) {
        expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
    }

    expect($other->refresh()->status)->toBe(CommitStatus::Pending);
});

it('may only update the commits that are still pending', function (): void {
    $pending = Commit::factory()->pending()->create();
    $rejected = Commit::factory()->rejected()->create();

    $action = resolve(UpdateCommitStatuses::class);

    $action->handle([$pending->id, $rejected->id], CommitStatus::Approved, onlyPending: true);

    expect($pending->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($rejected->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('notifies the parent authors of the approved replies', function (): void {
    $parent = Commit::factory()->create();
    $replies = Commit::factory()->pending()->for($parent, 'parent')->count(2)->create();
    $rejected = Commit::factory()->rejected()->for($parent, 'parent')->create();

    $action = resolve(UpdateCommitStatuses::class);

    $action->handle([...$replies->modelKeys(), $rejected->id], CommitStatus::Approved, onlyPending: true);

    expect($parent->user->notifications()->pluck('commit_id')->all())
        ->toEqualCanonicalizing($replies->modelKeys());
});

it('notifies the quoted authors of the approved quotes', function (): void {
    $quoted = Commit::factory()->create();
    $quotes = Commit::factory()->pending()->for($quoted, 'quotedCommit')->count(2)->create();
    $rejected = Commit::factory()->rejected()->for($quoted, 'quotedCommit')->create();

    $action = resolve(UpdateCommitStatuses::class);

    $action->handle([...$quotes->modelKeys(), $rejected->id], CommitStatus::Approved, onlyPending: true);

    expect($quoted->user->notifications()->pluck('commit_id')->all())
        ->toEqualCanonicalizing($quotes->modelKeys());
});

it('lifts the thread when a reply is approved', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->pending()->for($commit, 'parent')->create();

    resolve(UpdateCommitStatuses::class)->handle([$reply->id], CommitStatus::Approved);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 1), 1e-9);
});

it('lifts the quoted thread when a quote is approved', function (): void {
    $commit = Commit::factory()->create();
    $quote = Commit::factory()->pending()->for($commit, 'quotedCommit')->create();

    resolve(UpdateCommitStatuses::class)->handle([$quote->id], CommitStatus::Approved);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 1), 1e-9);
});
