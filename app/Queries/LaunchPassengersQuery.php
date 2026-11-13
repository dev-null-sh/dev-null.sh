<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class LaunchPassengersQuery
{
    /**
     * Only public identity fields leave this endpoint. The ship renders at most sixteen passengers regardless of crew size.
     *
     * @return array{count: int, boarded: bool, passengers: list<array{id: string, handle: string, name: string, avatar_url: string|null}>}
     */
    public function get(Commit $commit, ?User $viewer): array
    {
        $passengers = $commit->launchPassengers()->whereNull('users.banned_at');

        if ($viewer instanceof User) {
            $passengers->whereNotIn('users.id', $viewer->idsAcrossBlocks());
        }

        return [
            'count' => (clone $passengers)->count(),
            'boarded' => $viewer instanceof User && (clone $passengers)->where('users.id', $viewer->id)->exists(),
            'passengers' => array_values((clone $passengers)
                ->latest('launch_passengers.created_at')
                ->orderByDesc('users.id')
                ->limit(16)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'handle' => $user->handle,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                ])->all()),
        ];
    }
}
