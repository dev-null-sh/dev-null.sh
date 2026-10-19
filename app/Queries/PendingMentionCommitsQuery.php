<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class PendingMentionCommitsQuery
{
    /**
     * Whether anyone may see a commit that mentions a username nobody on /dev/null has, which gives it a profile to claim.
     */
    public function exists(string $username): bool
    {
        return $this->mentioning($username)->visibleTo(null)->exists();
    }

    /**
     * The commits and replies that mention a username nobody on /dev/null has, as the viewer, or a guest, may see them,
     * the replies with the parents the viewer may see.
     */
    public function builder(string $username, ?User $viewer): CommitBuilder
    {
        return $this->mentioning($username)
            ->visibleTo($viewer)
            ->withVisibleParent($viewer)
            ->withVisibleReplyCount($viewer)
            ->withReplyPreviews($viewer)
            ->withCardDataFor($viewer)
            ->newestFirst();
    }

    private function mentioning(string $username): CommitBuilder
    {
        return Commit::query()->whereHas('pendingMentions', fn (Builder $query): Builder => $query->where('username', Str::lower($username)));
    }
}
