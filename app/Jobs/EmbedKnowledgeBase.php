<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateKnowledgeBaseSections;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([60, 600])]
#[Tries(3)]
final class EmbedKnowledgeBase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $source = 'KNOWLEDGE_BASE.md')
    {
        //
    }

    /**
     * Only one import per source runs at a time, so overlapping runs never race on the same section positions.
     */
    public function uniqueId(): string
    {
        return $this->source;
    }

    /**
     * Unchanged sections reuse their stored vectors, so a run without changes makes no OpenAI requests. A failed
     * import leaves the stored sections untouched, and the next scheduled run picks it up again.
     */
    public function handle(UpdateKnowledgeBaseSections $action): void
    {
        $action->handle($this->source);
    }
}
