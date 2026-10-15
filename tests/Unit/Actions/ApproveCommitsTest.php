<?php

declare(strict_types=1);

use App\Actions\ApproveCommits;
use App\Enums\CommitStatus;
use App\Models\Commit;

it('may approve many commits', function (): void {
    $commits = Commit::factory()->pending()->count(2)->create();
    $other = Commit::factory()->pending()->create();

    $action = resolve(ApproveCommits::class);

    $action->handle($commits->modelKeys());

    foreach ($commits as $commit) {
        expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
    }

    expect($other->refresh()->status)->toBe(CommitStatus::Pending);
});

it('notifies the parent authors of the approved replies', function (): void {
    $parent = Commit::factory()->create();
    $replies = Commit::factory()->pending()->for($parent, 'parent')->count(2)->create();
    $rejected = Commit::factory()->rejected()->for($parent, 'parent')->create();

    $action = resolve(ApproveCommits::class);

    $action->handle([...$replies->modelKeys(), $rejected->id]);

    expect($parent->user->notifications()->pluck('commit_id')->all())
        ->toEqualCanonicalizing($replies->modelKeys());
});
