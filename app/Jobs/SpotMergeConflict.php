<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateMergeConflict;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Waits a few minutes after a reply, so a burst of replies is read together. The lock lets go once the read starts, so a
 * reply that comes in during it queues the next read.
 */
#[Backoff([60, 600])]
#[Timeout(120)]
#[Tries(2)]
#[UniqueFor(3600)]
final class SpotMergeConflict implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $commitId)
    {
        //
    }

    /**
     * Looks at the threads the given approved replies are in, once each. A thread Tom read within the hour waits until
     * he may read it again, so a reply that came in meanwhile still gets read.
     */
    public static function dispatchFor(Commit ...$replies): void
    {
        $parentIds = array_unique(array_filter(array_map(fn (Commit $reply): ?string => $reply->parent_id, $replies)));

        foreach ($parentIds as $parentId) {
            $readAt = resolve(CreateMergeConflict::class)->nextReadAt($parentId)->max(now()->addMinutes(5));

            dispatch(new self($parentId))->delay($readAt)->afterCommit();
        }
    }

    public function uniqueId(): string
    {
        return $this->commitId;
    }

    public function handle(CreateMergeConflict $action): void
    {
        $commit = Commit::query()->find($this->commitId);

        if ($commit instanceof Commit) {
            $action->handle($commit);
        }
    }
}
