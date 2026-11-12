<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\ModerationProvider;
use App\Enums\CommitStatus;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\PollOption;
use App\Queries\AdminsQuery;
use App\Services\EncodedText;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateCommitAiDecision
{
    /**
     * A category scoring this much leaves no doubt, so the commit is rejected without waiting for a moderator.
     */
    private const float AUTO_REJECT_THRESHOLD = 0.95;

    public function __construct(
        private ModerationProvider $moderation,
        private UpdateCommitStatuses $updateCommitStatuses,
        private EncodedText $encodedText,
        private AdminsQuery $admins,
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

        $text = $this->text($commit);

        $scores = $this->moderation->moderate($text, $images);

        $categories = array_keys($scores);

        if ($text !== '' && $this->moderation->isHarmful($text)) {
            $categories[] = 'harmful-text';
        }

        $commit->update([
            'ai_decision' => $categories === [] ? CommitStatus::Approved : CommitStatus::Rejected,
            'ai_categories' => $categories,
        ]);

        if ($scores !== [] && max($scores) >= self::AUTO_REJECT_THRESHOLD) {
            $this->updateCommitStatuses->handle([$commit->id], CommitStatus::Rejected, onlyPending: true);
        } elseif ($categories === [] && $commit->user !== null) {
            $this->updateCommitStatuses->handle([$commit->id], CommitStatus::Approved, onlyPending: true);
        } else {
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['commits' => [$commit->id]]));
        }
    }

    /**
     * All the text that goes public with the commit: the body, the image descriptions, and the poll options. A clean
     * commit is approved, so nothing the author wrote may be left unchecked.
     */
    private function text(Commit $commit): string
    {
        return collect([$commit->body, $this->encodedText->decode($commit->body)['body'] ?? null])
            ->merge($commit->images->map(fn (CommitImage $image): ?string => $image->description))
            ->merge($commit->poll?->options->map(fn (PollOption $option): string => $option->label) ?? [])
            ->filter(fn (?string $part): bool => $part !== null && $part !== '')
            ->implode("\n\n");
    }
}
