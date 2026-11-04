<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Support\Collection;

final readonly class SuggestedUserStatesQuery
{
    /**
     * @param  list<string>  $ids
     * @return Collection<int, array{id: string, is_following: bool, is_dismissed: bool}>
     */
    public function get(User $viewer, array $ids): Collection
    {
        $following = $viewer->following()->whereKey($ids)->pluck('users.id');
        $dismissed = $viewer->dismissedSuggestions()->whereKey($ids)->pluck('users.id');

        return collect($ids)->map(fn (string $id): array => [
            'id' => $id,
            'is_following' => $following->contains($id),
            'is_dismissed' => $dismissed->contains($id),
        ]);
    }
}
