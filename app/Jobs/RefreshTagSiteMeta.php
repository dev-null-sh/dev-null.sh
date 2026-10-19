<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RefreshTagSiteMeta as RefreshTagSiteMetaAction;
use App\Models\Tag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshTagSiteMeta implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $tagId = null)
    {
        //
    }

    public function handle(RefreshTagSiteMetaAction $action): void
    {
        if ($this->tagId === null) {
            $action->handleVerified();

            return;
        }

        $tag = Tag::query()->find($this->tagId);

        if ($tag instanceof Tag) {
            $action->handle($tag);
        }
    }
}
