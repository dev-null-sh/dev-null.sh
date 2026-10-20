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
     * Embeds the current body without overwriting an amendment made while the request was in flight. Failures are
     * thrown, so the caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $embedding = $this->openAI->embed($commit->body);

        Commit::query()->whereKey($commit->id)->where('revision_id', $commit->revision_id)->where('body', $commit->body)
            ->update(['embedding' => json_encode($embedding, JSON_THROW_ON_ERROR)]);
    }
}
