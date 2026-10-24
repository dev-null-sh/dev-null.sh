<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ClassifyCommitTopics;
use App\Models\Commit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('commits:classify-topics {--batch-size=20 : Commits per classification request (1–100)}')]
#[Description('Queue topic classification for undecided top-level commits on the low priority queue')]
final class ClassifyCommitTopicsCommand extends Command
{
    public function handle(): int
    {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        if ($batchSize === false) {
            $this->components->error('The batch size must be an integer between 1 and 100.');

            return self::INVALID;
        }

        $queued = 0;

        Commit::query()->select('id')->whereNull('parent_id')->whereNull('topic_decision')->where('body', '!=', '')
            ->chunkById($batchSize, function (Collection $commits) use (&$queued): void {
                dispatch(new ClassifyCommitTopics(array_values($commits->map(fn (Commit $commit): string => $commit->id)->all())));

                $queued += $commits->count();
            });

        $this->components->info("Queued {$queued} commits for topic classification.");

        return self::SUCCESS;
    }
}
