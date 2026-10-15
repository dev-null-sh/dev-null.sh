<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateCommitEvaluation;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Tries(3)]
#[Timeout(60)]
#[DeleteWhenMissingModels]
final class EvaluateCommitJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->commit->id;
    }

    public function handle(CreateCommitEvaluation $createCommitEvaluation): void
    {
        $createCommitEvaluation->handle($this->commit);
    }
}
