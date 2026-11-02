<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessCommitHeadlines;
use App\Models\Commit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('commits:link-headlines {--batch-size='.ProcessCommitHeadlines::BATCH_SIZE.' : Commits per batch (1–100)}')]
#[Description('Classify all unprocessed approved root commits in lazy batches and link shared headlines')]
final class LinkCommitHeadlinesCommand extends Command
{
    public function handle(): int
    {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        if ($batchSize === false) {
            $this->components->error('The batch size must be an integer between 1 and 100.');

            return self::FAILURE;
        }

        $processed = 0;

        $commits = Commit::query()->approved()->whereNull('parent_id')->where('body', '!=', '')
            ->whereNull('headlines_classified_at')->whereDoesntHave('headlines')->select('id')->lazyById($batchSize);

        foreach ($commits->chunk($batchSize) as $batch) {
            dispatch_sync(new ProcessCommitHeadlines(array_values($batch->map(fn (Commit $commit): string => $commit->id)->all())));
            $processed += $batch->count();
        }

        $this->components->info("Processed {$processed} commits for headlines.");

        return self::SUCCESS;
    }
}
