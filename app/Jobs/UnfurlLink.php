<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FetchLinkPreview;
use App\Models\LinkPreview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

#[Tries(1)]
#[DeleteWhenMissingModels]
final class UnfurlLink implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LinkPreview $linkPreview)
    {
        //
    }

    /**
     * Commits that share a link may each queue this job before the first one runs, so only the first one fetches.
     */
    public function handle(FetchLinkPreview $action): void
    {
        if ($this->linkPreview->fetched_at !== null) {
            return;
        }

        $action->handle($this->linkPreview);
    }
}
