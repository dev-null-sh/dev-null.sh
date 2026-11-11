<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class UnclaimedProfileMentionsQuery
{
    /**
     * The commits and replies that mention a developer who isn't on /dev/null yet, on the Mentions tab of their page,
     * as the viewer, or a guest, may see them. Like an organization's, it's the community's feed rather than anyone's
     * profile, so muted authors are left out like in a log, and replies come with the parents the viewer may see.
     */
    public function builder(Profile $profile, ?User $viewer): CommitBuilder
    {
        return Commit::query()
            ->mentioning($profile)
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->with(['parent' => fn (BelongsTo $query): BelongsTo => $query
                ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
                ->with('author')])
            ->withVisibleReplyCount($viewer)
            ->withReplyPreviews($viewer)
            ->withCardDataFor($viewer)
            ->latest()
            ->latest('id');
    }
}
