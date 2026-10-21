<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\UnfurlLink;
use App\Models\LinkPreview;
use Throwable;

final readonly class CreateLinkPreviewJob
{
    public function handle(LinkPreview $linkPreview): void
    {
        $preview = $linkPreview->fresh();

        if (! $preview instanceof LinkPreview || $preview->fetched_at !== null) {
            return;
        }

        $job = new UnfurlLink($preview);

        try {
            dispatch($job)->afterCommit();
        } catch (Throwable $throwable) {
            $job->failed();

            throw $throwable;
        }
    }
}
