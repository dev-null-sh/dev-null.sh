<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateExploreWork;
use App\Jobs\ProcessExplore;
use App\Jobs\SummarizeExplore;
use App\Models\Commit;
use App\Queries\ExploreContentQuery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Description('Dispatch due Explore classification and summary work')]
#[Signature('explore:process {--backfill : Admit approved commits from the preceding seven days} {--force : Process a partial backlog now}')]
final class ProcessExploreCommand extends Command
{
    public function handle(ExploreContentQuery $content, CreateExploreWork $admit): void
    {
        if ($this->option('backfill')) {
            $content->eligible()->where('created_at', '>=', now()->subDays(7))->oldest()->orderBy('id')
                ->chunk(500, fn (Collection $commits) => $admit->handle(array_values($commits->map(fn (Commit $commit): string => $commit->id)->all()), backfill: true));
        }

        $oldest = $content->waiting()->value('eligible_at');
        if (is_string($oldest) && ($this->option('force') || $content->waiting()->count() >= config()->integer('explore.cohort_size')
            || now()->parse($oldest)->lte(now()->subMinutes(config()->integer('explore.wait_minutes'))))) {
            dispatch(new ProcessExplore());
        }

        dispatch(new SummarizeExplore());
        $this->components->info('Due Explore work dispatched.');
    }
}
