<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\LazyCollection;

#[Queue('low')]
final class BackfillCommitTopics implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $batchSize)
    {
        //
    }

    public function handle(): void
    {
        Commit::query()->approved()->whereNull('parent_id')->where('body', '!=', '')
            ->whereDoesntHave('topics')->lazyById($this->batchSize)->chunk($this->batchSize)
            ->each(fn (LazyCollection $commits): PendingDispatch => dispatch(new ClassifyCommitTopics(array_values($commits->map(fn (Commit $commit): string => $commit->id)->all()))));
    }
}
