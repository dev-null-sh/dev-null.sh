<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\CommitRevision;
use App\Services\OpenAI;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateCommitAiDecision
{
    /**
     * A category scoring this much leaves no doubt, so the commit is rejected without waiting for a moderator.
     */
    private const float AUTO_REJECT_THRESHOLD = 0.95;

    public function __construct(
        private OpenAI $openAI,
        private UpdateCommitStatuses $updateCommitStatuses,
    ) {
        //
    }

    /**
     * Asks the AI whether the commit, images included, should be approved. The decision is mostly a hint for
     * moderators: a clean commit is approved, and it is rejected, without a reason, when the AI is all but certain.
     * Failures, such as timeouts or rate limits, are thrown, so the caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $images = array_values($commit->images->map(
            fn (CommitImage $image): string => 'data:image/webp;base64,'.base64_encode((string) Storage::get($image->path)),
        )->all());

        $body = $commit->body;
        $scores = $this->openAI->moderate($body, $images);
        $categories = array_keys($scores);
        $previousBodies = array_values($commit->revisions()->reorder()->oldest()->oldest('id')->get()
            ->map(fn (CommitRevision $previous): string => $previous->body)->all());

        if (($body !== '' || $previousBodies !== []) && ($previousBodies === []
            ? $this->openAI->isHarmful($body) : $this->openAI->isHarmful($body, $previousBodies))) {
            $categories[] = 'harmful-text';
        }

        if ($images !== [] && $this->openAI->showsMinors($images)) {
            $categories[] = 'minor-in-image';
        }

        DB::transaction(function () use ($commit, $categories, $scores): void {
            $current = Commit::query()->whereKey($commit->id)->lockForUpdate()->first();

            if (! $current instanceof Commit || $current->version() !== $commit->version() || $current->body !== $commit->body) {
                return;
            }

            $current->update([
                'ai_decision' => $categories === [] ? CommitStatus::Approved : CommitStatus::Rejected,
                'ai_categories' => $categories,
            ]);

            $status = $scores !== [] && max($scores) >= self::AUTO_REJECT_THRESHOLD
                ? CommitStatus::Rejected
                : ($categories === [] && $current->user !== null ? CommitStatus::Approved : null);

            if ($status !== null) {
                $this->updateCommitStatuses->handle([$current->id], $status, onlyPending: true);
            }
        });
    }
}
