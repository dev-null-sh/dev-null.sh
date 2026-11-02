<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\LinkPreviewProvider;
use App\Models\LinkPreview;

final readonly class UpdateLinkPreview
{
    public function __construct(private LinkPreviewProvider $linkPreviews)
    {
        //
    }

    /**
     * Stores the Open Graph metadata of the page behind the link. A page that can't or mustn't be fetched is marked as
     * fetched without metadata, so it is not tried again. Failures that may pass are thrown, so the caller can try again
     * later.
     */
    public function handle(LinkPreview $linkPreview): void
    {
        $linkPreview->update([...$this->linkPreviews->metadata($linkPreview->url), 'fetched_at' => now()]);
    }
}
