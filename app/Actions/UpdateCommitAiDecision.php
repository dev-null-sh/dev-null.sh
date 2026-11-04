<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\ModerationProvider;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\PollOption;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final readonly class UpdateCommitAiDecision
{
    /**
     * A category scoring this much leaves no doubt, so the commit is rejected without waiting for a moderator.
     */
    private const float AUTO_REJECT_THRESHOLD = 0.95;

    private const int GIF_MAX_BYTES = 20 * 1024 * 1024;

    private const int GIF_MAX_DIMENSION = 1600;

    public function __construct(
        private ModerationProvider $moderation,
        private UpdateCommitStatuses $updateCommitStatuses,
    ) {
        //
    }

    /**
     * Asks the AI whether the commit, images and GIF included, should be approved. The decision is mostly a hint for
     * moderators: a clean commit is approved, and it is rejected, without a reason, when the AI is all but certain.
     * Failures, such as timeouts or rate limits, are thrown, so the caller can try again later.
     */
    public function handle(Commit $commit): void
    {
        $images = array_values($commit->images->map(
            fn (CommitImage $image): string => 'data:image/webp;base64,'.base64_encode((string) Storage::get($image->path)),
        )->all());

        if ($commit->gif !== null) {
            $images[] = $this->gifForModeration($commit->gif->url);
        }

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
        }

        if ($categories === [] && $commit->user !== null) {
            $this->updateCommitStatuses->handle([$commit->id], CommitStatus::Approved, onlyPending: true);
        }
    }

    /**
     * All the text that goes public with the commit: the body, the image descriptions, and the poll options. A clean
     * commit is approved, so nothing the author wrote may be left unchecked.
     */
    private function text(Commit $commit): string
    {
        return collect([$commit->body])
            ->merge($commit->images->map(fn (CommitImage $image): ?string => $image->description))
            ->merge($commit->poll?->options->map(fn (PollOption $option): string => $option->label) ?? [])
            ->filter(fn (?string $part): bool => $part !== null && $part !== '')
            ->implode("\n\n");
    }

    /**
     * The moderation endpoint only accepts non-animated GIFs of at most 20 MB, while providers serve animated ones.
     * Sending their first frame as WebP keeps the check on the image itself instead of relying on the browser filter.
     */
    private function gifForModeration(string $url): string
    {
        $response = Http::timeout(10)->withoutRedirecting()->get($url)->throw();
        $body = $response->body();

        if (mb_strlen($body) > self::GIF_MAX_BYTES) {
            throw new RuntimeException('The GIF is too large to moderate.');
        }

        try {
            $webp = Image::fromBytes($body)
                ->usingGd()
                ->scale(self::GIF_MAX_DIMENSION, self::GIF_MAX_DIMENSION)
                ->optimize('webp', 60)
                ->toBytes();
        } catch (Throwable $throwable) {
            throw new RuntimeException('The GIF could not be prepared for moderation.', $throwable->getCode(), previous: $throwable);
        }

        return 'data:image/webp;base64,'.base64_encode($webp);
    }
}
