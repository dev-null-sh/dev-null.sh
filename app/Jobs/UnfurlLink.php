<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateLinkPreview;
use App\Models\LinkPreview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;

#[Backoff([60, 600])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class UnfurlLink implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // Allow foreign-key references while serializing workers.
    private const string METADATA_LOCK = 'for no key update';

    // Default supports older queued payloads.
    public int $publicationUnfurlRetries = 0;

    public function __construct(public readonly LinkPreview $linkPreview)
    {
        if ($linkPreview->publication_unfurl_retries === 0) {
            return;
        }

        $this->publicationUnfurlRetries = $linkPreview->publication_unfurl_retries;
    }

    public function uniqueId(): string
    {
        return $this->linkPreview->id;
    }

    public function handle(UpdateLinkPreview $action): void
    {
        DB::transaction(function () use ($action): void {
            $linkPreview = LinkPreview::query()->lock(self::METADATA_LOCK)->find($this->linkPreview->id);

            if (! $linkPreview instanceof LinkPreview
                || $linkPreview->fetched_at !== null
                || $linkPreview->publication_unfurl_retries !== $this->publicationUnfurlRetries
            ) {
                return;
            }

            $action->handle($linkPreview);
        });
    }

    public function failed(): void
    {
        $this->linkPreview->markUnfurlAsTransientFailure($this->publicationUnfurlRetries);
    }
}
