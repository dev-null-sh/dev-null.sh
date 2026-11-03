<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateCommitTranslation;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
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
     * Every try failed at the provider (an error or an answer it can't use), so views stop queueing detection for this
     * post until the mark expires. Anything else, like the post changing while it was detected or a timeout, says
     * nothing about the post, so it's left to be detected again on the next view.
     */
    public function failed(?Throwable $exception): void
    {
        if (! $exception instanceof CommitProcessingFailed) {
            return;
        }

        $commit = Commit::withTrashed()->find($this->commitId);

        if ($commit instanceof Commit) {
            resolve(CommitTranslations::class)->markUndetectable($commit);
        }
    }
}
