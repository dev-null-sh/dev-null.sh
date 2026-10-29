<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateMergeConflict;
use App\Actions\UpdateCommitStatuses;
use App\Ai\Agents\ConflictSpotter;
use App\Enums\CommitStatus;
use App\Jobs\SpotMergeConflict;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('looks at the thread of each reply once, a few minutes later', function (): void {
    Queue::fake([SpotMergeConflict::class]);
    $commit = Commit::factory()->create();

    SpotMergeConflict::dispatchFor(...Commit::factory()->count(2)->create(['parent_id' => $commit->id])->all());

    Queue::assertPushedTimes(SpotMergeConflict::class, 1);
    Queue::assertPushed(SpotMergeConflict::class, fn (SpotMergeConflict $job): bool => $job->commitId === $commit->id
        && $job->delay?->toDateTimeString() === now()->addMinutes(5)->toDateTimeString());
});

it('waits for Tom to be able to read a thread he read within the hour', function (): void {
    ConflictSpotter::fake([['conflict' => false, 'ours' => 0, 'theirs' => 0]]);
    $commit = Commit::factory()->create();
    Commit::factory()->count(2)->create(['parent_id' => $commit->id]);
    resolve(CreateMergeConflict::class)->handle($commit);
    $readAgainAt = now()->addHour();
    Queue::fake([SpotMergeConflict::class]);

    $this->travel(10)->minutes();
    SpotMergeConflict::dispatchFor(Commit::factory()->create(['parent_id' => $commit->id]));

    Queue::assertPushed(SpotMergeConflict::class, fn (SpotMergeConflict $job): bool => $job->delay?->toDateTimeString() === $readAgainAt->toDateTimeString());
});

it('looks at the thread once a pending reply is approved', function (): void {
    Queue::fake([SpotMergeConflict::class]);
    $commit = Commit::factory()->create();
    $replies = Commit::factory()->pending()->count(2)->create(['parent_id' => $commit->id]);

    resolve(UpdateCommitStatuses::class)->handle($replies->modelKeys(), CommitStatus::Approved);

    Queue::assertPushedTimes(SpotMergeConflict::class, 1);
});

it('leaves commits that are not replies alone', function (): void {
    Queue::fake([SpotMergeConflict::class]);

    resolve(CreateCommit::class)->handle(User::factory()->create(), 'Hello');

    Queue::assertNotPushed(SpotMergeConflict::class);
});
