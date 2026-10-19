<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final readonly class MaintainedOrgsQuery
{
    public function __construct(private RemoteCandidatesQuery $candidates)
    {
        //
    }

    /**
     * The orgs the user maintains, most recently active first, each with the reason the candidate can't be added to
     * it, if there is one, so a profile's menu can offer them. None when either of them has remotes off, or when the
     * candidate doesn't follow the user, unless the user is a site admin. Each comes with a few of its other members,
     * to name it by when it has no name.
     *
     * @return Collection<int, array{id: string, name: string|null, members: Collection<int, array{user: User}>, reason: string|null}>
     */
    public function get(User $user, User $candidate): Collection
    {
        if (! $user->remotes_enabled || ! $candidate->remotes_enabled || $user->is($candidate) || (! $user->isAdmin() && ! $candidate->isFollowing($user))) {
            return collect();
        }

        return Conversation::query()
            ->where('kind', ConversationKind::Org)
            ->whereHas('participants', fn (Builder $query): Builder => $query
                ->where('user_id', $user->id)
                ->where('role', OrgRole::Maintainer))
            ->with(['participants' => fn (HasMany $query): HasMany => $query
                ->whereNot('user_id', $user->id)
                ->oldest('id')
                ->limit(3)
                ->with('user')])
            ->orderByDesc('last_message_id')
            ->get()
            ->map(fn (Conversation $org): array => [
                'id' => $org->id,
                'name' => $org->name,
                'members' => $org->participants->map(fn (ConversationParticipant $participant): array => ['user' => $participant->user])->toBase(),
                'reason' => $this->candidates->reasons($user, collect([$candidate]), $org)[$candidate->id],
            ])
            ->toBase();
    }
}
