<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateCommitTranslation;
use App\Models\LinkPreview;
use App\Services\CommitTranslations;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Catches a preview up with a post that is already shown translated, such as one translated before its link was
 * unfurled, or one whose preview could not be translated on the first try.
 */
#[Backoff([60, 600])]
#[Queue('translations')]
#[Timeout(120)]
#[Tries(3)]
#[UniqueFor(3600)]
final class TranslateLinkPreview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $linkPreviewId, public readonly string $language)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->linkPreviewId.':'.$this->language;
    }

    public function handle(CreateCommitTranslation $action, CommitTranslations $translations): void
    {
        $linkPreview = LinkPreview::query()->find($this->linkPreviewId);

        if ($linkPreview instanceof LinkPreview && $translations->enabled() && $translations->isTranslatable($linkPreview)) {
            $action->translateLinkPreview($linkPreview, $this->language);
        }
    }
}
