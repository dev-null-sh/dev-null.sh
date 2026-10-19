<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class RepoContributorCandidatesQuery
{
    public function __construct(private UserSearchQuery $search)
    {
        //
    }

    /**
     * Developers matching the term, each with the reason the maintainer can't add them to the repo, if there is one,
     * so the picker can show who is out of reach and why.
     *
     * @return Collection<int, array{user: User, reason: string|null}>
     */
    public function get(User $maintainer, string $term, Repo $repo): Collection
    {
        $candidates = $this->search->get($term, $maintainer)->reject(fn (User $candidate): bool => $candidate->is($maintainer))->values();
        $reasons = $this->reasons($maintainer, $candidates, $repo);

        return $candidates->map(fn (User $candidate): array => [
            'user' => $candidate,
            'reason' => $reasons[$candidate->id],
        ]);
    }

    /**
     * Into a repo, a maintainer can add people who follow them and aren't in it yet.
     *
     * @param  Collection<int, User>  $candidates
     * @return array<string, string|null>
     */
    public function reasons(User $maintainer, Collection $candidates, Repo $repo): array
    {
        $ids = $candidates->pluck('id');
        $contributors = $repo->contributors()->whereIn('user_id', $ids)->pluck('user_id');
        $followers = $maintainer->followers()->newPivotQuery()->whereIn('follower_id', $ids)->pluck('follower_id');

        return $candidates->mapWithKeys(fn (User $candidate): array => [$candidate->id => match (true) {
            $contributors->contains($candidate->id) => 'already contributes',
            $followers->doesntContain($candidate->id) => "doesn't follow you",
            default => null,
        }])->all();
    }
}
