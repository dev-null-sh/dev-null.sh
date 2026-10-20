<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Section;
use App\Models\Commit;
use App\Models\User;

final readonly class SectionLogQuery
{
    /**
     * The only place the commits of a section are listed, newest first, as top-level commits the viewer may see.
     */
    public function builder(?User $user, Section $section): CommitBuilder
    {
        return Commit::query()
            ->inSection($section)
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->latest()
            ->latest('id');
    }
}
