<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitTopicDecisions;
use App\Exceptions\CommitProcessingFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Backoff([60, 600])]
#[Queue('low')]
#[Tries(3)]
final class ClassifyCommitTopics implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $commitIds
     */
    public function __construct(public readonly array $commitIds)
    {
        //
    }

    public function handle(UpdateCommitTopicDecisions $action): void
    {
        try {
            $action->handle($this->commitIds);

            foreach (array_reverse(array_chunk($this->commitIds, 20)) as $batch) {
                $this->prependToChain(new AssignCommitTopics($batch));
            }
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Topic classification', implode(', ', $this->commitIds), $throwable);
        }
    }
}
