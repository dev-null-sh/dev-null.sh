<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RemoteCandidatesQuery
{
    public function __construct(private UserSearchQuery $search, private PushAccessQuery $pushAccess)
    {
        //
    }

    /**
     * Developers matching the term, each with the reason the user can't message them or add them to the org, if
     * there is one, so pickers can show who is out of reach and why.
     *
     * @return Collection<int, array{user: User, reason: string|null}>
     */
    public function get(User $user, string $term, ?Conversation $org = null, bool $forSharing = false): Collection
    {
        $candidates = $this->search->get($term, $user)->reject(fn (User $candidate): bool => $candidate->is($user))->values();
        $reasons = $this->reasons($user, $candidates, $org, forSharing: $forSharing);

        return $candidates->map(fn (User $candidate): array => [
            'user' => $candidate,
            'reason' => $reasons[$candidate->id],
        ]);
    }

    /**
     * Into an org, a maintainer can add people who follow them and have remotes on, up to the org's limit, and
     * not across a block with the maintainer, anyone already in it, or anyone joining with them. Site admins can add
     * anyone with remotes on.
     *
     * @param  Collection<int, User>  $candidates
     * @param  Collection<int, User>|null  $joining
     * @return array<string, string|null>
     */
    public function reasons(User $user, Collection $candidates, ?Conversation $org = null, ?Collection $joining = null, bool $forSharing = false): array
    {
        if (! $org instanceof Conversation) {
            return $candidates->mapWithKeys(fn (User $candidate): array => [$candidate->id => match ($this->pushAccess->get($user, $candidate)) {
                PushAccess::Closed => 'has remotes off',
                PushAccess::Restricted => "doesn't follow you",
                PushAccess::Request => $forSharing ? 'merge their request first' : null,
                PushAccess::Waiting => $forSharing ? 'your request is waiting to be merged' : null,
                default => null,
            }])->all();
        }

        $ids = $candidates->pluck('id');
        $members = $org->participants()->pluck('user_id');
        $followers = $user->followers()->newPivotQuery()->whereIn('follower_id', $ids)->pluck('follower_id');
        $others = $members->push($user->id)->merge($joining?->pluck('id') ?? []);
        $blocked = DB::table('blocks')
            ->where(fn (Builder $query): Builder => $query->whereIn('blocker_id', $others)->whereIn('blocked_id', $ids))
            ->orWhere(fn (Builder $query): Builder => $query->whereIn('blocker_id', $ids)->whereIn('blocked_id', $others))
            ->get(['blocker_id', 'blocked_id'])
            ->flatMap(fn (object $block): array => [$block->blocker_id, $block->blocked_id]);
        $room = Conversation::MAX_MEMBERS - $org->participants()->count();

        return $candidates->mapWithKeys(fn (User $candidate): array => [$candidate->id => match (true) {
            $members->contains($candidate->id) => 'already in the org',
            ! $candidate->remotes_enabled => 'has remotes off',
            ! $user->isAdmin() && $followers->doesntContain($candidate->id) => "doesn't follow you",
            $blocked->contains($candidate->id) => 'has a block with someone in the org',
            $room <= 0 => 'the org is full ('.Conversation::MAX_MEMBERS.')',
            default => null,
        }])->all();
    }
}
