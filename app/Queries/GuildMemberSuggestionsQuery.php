<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guild;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class GuildMemberSuggestionsQuery
{
    /**
     * The members who could take over moderating the guild: never its current moderator. Without a term, the most recent joiners;
     * with one, handles and names that contain it, exact handles and prefixes first.
     *
     * @return Collection<int, array{id: string, handle: string, name: string, avatar_url: string|null}>
     */
    public function get(Guild $guild, string $term, int $limit = 8): Collection
    {
        $term = Str::of($term)->trim()->ltrim('@')->lower()->value();
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return $guild->members()
            ->select('users.*')
            ->join('profiles', 'profiles.id', '=', 'users.id')
            ->when($guild->user_id !== null, fn (Builder $query): Builder => $query->whereKeyNot($guild->user_id))
            ->when($term !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereRaw("LOWER(profiles.handle) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                    ->orWhereRaw("LOWER(profiles.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
                ->orderByRaw(
                    "CASE WHEN LOWER(profiles.handle) = ? THEN 0 WHEN LOWER(profiles.handle) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(profiles.name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                    [$term, "{$escaped}%", "{$escaped}%"],
                ))
            ->orderByPivot('joined_at', 'desc')
            ->latest('users.id')
            ->limit($limit)
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'handle' => $member->handle,
                'name' => $member->name,
                'avatar_url' => $member->avatar_url,
            ])
            ->values()
            ->toBase();
    }
}
