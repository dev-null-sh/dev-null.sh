<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ExtractCommitTopics;
use App\Models\Commit;
use App\Models\Topic;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Tries(3)]
final class AssignCommitTopics implements ShouldQueue
{
    use Queueable;

    public function __construct(public Commit $commit)
    {
        //
    }

    public function handle(ExtractCommitTopics $action): void
    {
        $topicIds = array_map(
            fn (string $name): string => Topic::query()->firstOrCreate(['name' => $name])->id,
            $action->handle(mb_trim($this->commit->title."\n\n".$this->commit->description)),
        );

        $this->commit->topics()->sync($topicIds);
    }
}
