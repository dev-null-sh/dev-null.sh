<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateCommitTranslation;
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
        $commit = Commit::query()->approved()->find($this->commitId);

        if ($commit instanceof Commit && resolve(CommitTranslations::class)->enabled()) {
            $action->handle($commit);
        }
    }
}
