<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitTopics;
use App\Enums\CommitStatus;
use App\Models\Commit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;

/**
 * Runs once a commit is approved, so only commits everyone can read spend GitHub's rate limit, and again once the rate
 * limit resets when it cut the lookups short. Waiting for the reset doesn't count as a try: the job gives up after three
 * failures, or a day after it was queued.
 */
#[Backoff([60, 600])]
#[MaxExceptions(3)]
#[DeleteWhenMissingModels]
final class LinkCommitTopics implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    /**
     * Queues a lookup for each of the commits that is approved and mentions handles no user has. A pending commit is
     * looked up once it is approved, so call this again then. Safe to call more than once.
     */
    public static function dispatchFor(Commit ...$commits): void
    {
        $mentioning = array_filter($commits, fn (Commit $commit): bool => $commit->mentionedNonUsers() !== []);

        if ($mentioning === []) {
            return;
        }

        Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, array_values($mentioning)))
            ->where('status', CommitStatus::Approved)
            ->get()
            ->each(fn (Commit $commit) => dispatch(new self($commit))->afterCommit());
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay();
    }

    public function handle(UpdateCommitTopics $action): void
    {
        $retryAt = $action->handle($this->commit);

        if ($retryAt instanceof CarbonImmutable) {
            $this->release($retryAt);
        }
    }
}
