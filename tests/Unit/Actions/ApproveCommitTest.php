<?php

declare(strict_types=1);

use App\Actions\ApproveCommit;
use App\Enums\CommitStatus;
use App\Jobs\AssignCommitTopics;
use App\Models\Commit;
use Illuminate\Support\Facades\Queue;

it('may approve a commit', function (): void {
    $commit = Commit::factory()->pending()->create();

    $action = resolve(ApproveCommit::class);

    $action->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('queues an approved commit to be filed under topics', function (): void {
    Queue::fake();
    $commit = Commit::factory()->pending()->create();

    resolve(ApproveCommit::class)->handle($commit);

    Queue::assertPushed(AssignCommitTopics::class, fn (AssignCommitTopics $job): bool => $job->commit->is($commit));
});
