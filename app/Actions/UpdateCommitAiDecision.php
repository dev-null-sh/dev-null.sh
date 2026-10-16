<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Services\OpenAI;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateCommitAiDecision
{
    public function __construct(private OpenAI $openAI)
    {
        //
    }

    /**
     * Asks the AI whether the commit, images included, should be approved. The decision is only a hint for
     * moderators: the commit's status is left alone. Failures, such as timeouts or rate limits, are thrown, so the
     * caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $images = array_values($commit->images->map(
            fn (CommitImage $image): string => 'data:image/webp;base64,'.base64_encode((string) Storage::get($image->path)),
        )->all());

        $categories = $this->openAI->moderate($commit->body, $images);

        if ($images !== [] && $this->openAI->showsMinors($images)) {
            $categories[] = 'minor-in-image';
        }

        $commit->update([
            'ai_decision' => $categories === [] ? CommitStatus::Approved : CommitStatus::Rejected,
            'ai_categories' => $categories,
        ]);
    }
}
