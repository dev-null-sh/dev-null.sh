<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

final readonly class OrganizationCommitsQuery
{
    /**
     * The organization's own top-level commits, on the Commits tab of its page, as the viewer, or a guest, may see them.
     */
    public function builder(Organization $organization, ?User $viewer): CommitBuilder
    {
        return Commit::query()
            ->where('author_id', $organization->id)
            ->visibleTo($viewer)
            ->topLevel()
            ->withVisibleReplyCount($viewer)
            ->withReplyPreviews($viewer)
            ->withCardDataFor($viewer)
            ->latest()
            ->latest('id');
    }
}
