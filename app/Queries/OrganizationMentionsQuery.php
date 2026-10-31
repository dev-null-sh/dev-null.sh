<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class OrganizationMentionsQuery
{
    /**
     * The commits and replies that mention the organization, on the Mentions tab of its page, as the viewer, or a
     * guest, may see them. It's the community's feed rather than anyone's profile, so muted authors are left out like
     * in a log, and replies come with the parents the viewer may see.
     */
    public function builder(Organization $organization, ?User $viewer): CommitBuilder
    {
        return Commit::query()
            ->mentioning($organization)
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
                ->with('user')])
            ->withVisibleReplyCount($viewer)
            ->withReplyPreviews($viewer)
            ->withCardDataFor($viewer)
            ->latest()
            ->latest('id');
    }
}
