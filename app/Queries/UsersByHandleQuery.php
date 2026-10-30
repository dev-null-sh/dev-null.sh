<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProfileType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UsersByHandleQuery
{
    /**
     * The users with these handles, keyed like the handles, whatever their case, like "@handle" URLs. A stale profile may
     * still hold the same handle in another case, so the exact case wins, then always the same one. Handles no user has
     * are left out.
     *
     * @param  array<int, string>  $handles
     * @return Collection<int, User>
     */
    public function get(array $handles): Collection
    {
        $users = User::query()
            ->with('profile')
            ->whereHas('profile', fn (Builder $query): Builder => $query->where('type', ProfileType::User)->whereIn(DB::raw('LOWER(handle)'), array_map(mb_strtolower(...), $handles)))
            ->get()
            ->sortBy(fn (User $user): string => $user->handle);

        return collect($handles)
            ->map(fn (string $handle): ?User => $users->first(fn (User $user): bool => $user->handle === $handle)
                ?? $users->first(fn (User $user): bool => mb_strtolower($user->handle) === mb_strtolower($handle)))
            ->filter();
    }
}
