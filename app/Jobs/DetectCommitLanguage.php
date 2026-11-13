<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateCommitTranslation;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Services\CommitTranslations;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Backoff([60, 600])]
#[Queue('translations')]
#[Timeout(120)]
#[Tries(3)]
#[UniqueFor(3600)]
final class DetectCommitLanguage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $commitId)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->commitId;
    }

    public function handle(CreateCommitTranslation $action): void
    {
        $commit = Commit::query()->whereIn('status', [CommitStatus::Approved, CommitStatus::Pending])->find($this->commitId);

        if ($commit instanceof Commit && resolve(CommitTranslations::class)->enabled()) {
            $action->handle($commit);
        }
    }

    /**
     * Detection that keeps failing is remembered for a while, so the next view does not queue it again, the way
     * GitHubReadmeQuery remembers a failed fetch. A body that changed since the dispatch is not marked.
     */
    public function failed(Throwable $exception): void
    {
        $commit = Commit::query()->find($this->commitId);

        if ($commit instanceof Commit) {
            Cache::put(resolve(CommitTranslations::class)->failedKey($commit), true, CommitTranslations::DETECTION_FAILURE_TTL_SECONDS);
        }
    }
}
