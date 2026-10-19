<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class BranchMemberCandidatesQuery
{
    public function __construct(private UserSearchQuery $search)
    {
        //
    }

    /**
     * Developers the maintainer can invite: people in their follow graph first. With a search term, broader matches
     * appear greyed out when they are already on the branch or outside the follow graph (admins skip that last gate).
     *
     * @return Collection<int, array{user: User, reason: string|null}>
     */
    public function get(User $actor, Branch $branch, string $term, int $limit = 8): Collection
    {
        $term = Str::of($term)->trim()->ltrim('@')->value();
        $candidates = $term === ''
            ? $this->followGraph($actor, $limit)
            : $this->search->get($term, $actor, $limit);

        $memberIds = $branch->members()->pluck('user_id');
        $graphIds = $this->followGraphIds($actor);

        return $candidates
            ->reject(fn (User $candidate): bool => $candidate->is($actor))
            ->values()
            ->map(fn (User $candidate): array => [
                'user' => $candidate,
                'reason' => match (true) {
                    $memberIds->contains($candidate->id) => 'already on the branch',
                    ! $actor->isAdmin() && $graphIds->doesntContain($candidate->id) => 'not in your follow graph',
                    $memberIds->count() >= Branch::MAX_MEMBERS => 'the branch is full ('.Branch::MAX_MEMBERS.')',
                    default => null,
                },
            ])
            ->sortBy(fn (array $candidate): array => [
                $candidate['reason'] === null ? 0 : 1,
                $candidate['user']->username,
            ])
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function followGraph(User $actor, int $limit): Collection
    {
        $ids = $this->followGraphIds($actor);

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return User::query()
            ->whereIn('id', $ids)
            ->whereNotIn('id', $actor->mutedIds())
            ->whereNotIn('id', $actor->idsAcrossBlocks())
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /**
     * @return Collection<int, string>
     */
    private function followGraphIds(User $actor): Collection
    {
        return $actor->following()->pluck('users.id')
            ->merge($actor->followers()->pluck('users.id'))
            ->unique()
            ->values();
    }
}
