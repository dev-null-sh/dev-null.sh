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
     * Embed the current body without overwriting a newer edit with a stale result. Failures, such as
     * timeouts or rate limits, are thrown, so the caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $embedding = $this->openAI->embed($commit->body);

        Commit::query()->whereKey($commit->id)->where('body', $commit->body)->update(['embedding' => $embedding]);
    }
}
