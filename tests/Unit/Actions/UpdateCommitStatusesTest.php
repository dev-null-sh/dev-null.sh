<?php

declare(strict_types=1);

use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Models\Commit;

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
