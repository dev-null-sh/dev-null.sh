<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Services\OpenAI;

final readonly class UpdateCommitEmbedding
{
    public function __construct(private OpenAI $openAI)
    {
        //
    }

    /**
     * Commits are immutable, so the body is embedded once, when the commit is created or backfilled. Failures, such as
     * timeouts or rate limits, are thrown, so the caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $commit->update(['embedding' => $this->openAI->embed($commit->body)]);
    }
}
