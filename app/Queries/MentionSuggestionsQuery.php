<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class MentionSuggestionsQuery
{
    /**
     * Without a term, the people the user follows, most recently followed first, so a bare "@" already has
     * suggestions. With one, the same matches as people search, people the user follows first within each tier.
     * Replies prioritize the recipient, root author, and visible thread contributors; quotes only prioritize the quoted author.
     * Within an org, only its members, and a bare "@" lists them all, people the user follows first. Never the user
     * themselves, people they mute, or anyone across a block.
     *
     * @return Collection<int, User>
     */
    public function get(User $user, string $term, ?Conversation $org = null, int $limit = 6, ?Commit $reply = null, ?Commit $quote = null): Collection
    {
        $term = Str::of($term)
            ->trim()
            ->ltrim('@')
            ->lower()
            ->value();

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        if ($term === '' && ! $org instanceof Conversation && ! $reply instanceof Commit && ! $quote instanceof Commit) {
            return $user->following()
                ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
                ->whereNotIn('users.id', $user->mutedIds())
                ->orderByPivot('created_at', 'desc')
                ->orderBy('profiles.handle')
                ->limit($limit)
                ->get()
                ->each(fn (User $followed): User => $followed->unsetRelation('pivot'))
                ->toBase();
        }

        $target = $reply ?? $quote;
        $root = $reply instanceof Commit ? Commit::query()->visibleTo($user)->find($reply->threadRootId()) : null;
        $contributors = $reply instanceof Commit
            ? Commit::query()->visibleTo($user)->where('root_id', $reply->threadRootId())->toBase()
            : null;

        return User::query()
            ->select('users.*')
            ->join('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->whereKeyNot($user->id)
            ->whereNotIn('users.id', $user->mutedIds())
            ->whereNotIn('users.id', $user->idsAcrossBlocks())
            ->when($org, fn (Builder $query, Conversation $org): Builder => $query->whereIn('users.id', $org->participants()->select('user_id')))
            ->when($term === '' && $target instanceof Commit, fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereIn('users.id', [$target?->user_id, $root?->user_id])
                    ->when($contributors, fn (Builder $query, QueryBuilder $contributors): Builder => $query->orWhereIn('users.id', (clone $contributors)->select('user_id')))
                    ->orWhereIn('users.id', $user->following()->select('users.id'))))
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(profiles.handle) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(profiles.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))
            ->when($target, fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN users.id = ? THEN 0 WHEN users.id = ? THEN 1 ELSE 2 END', [$target?->user_id, $root?->user_id]))
            ->when($contributors, fn (Builder $query, QueryBuilder $contributors): Builder => $query
                ->orderByDesc((clone $contributors)->selectRaw('COUNT(*) > 0')->whereColumn('commits.user_id', 'users.id')))
            ->orderByRaw(
                "CASE WHEN LOWER(profiles.handle) = ? THEN 0 WHEN LOWER(profiles.handle) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(profiles.name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$term, "{$escaped}%", "{$escaped}%"],
            )
            ->orderByDesc(fn (QueryBuilder $query): QueryBuilder => $query
                ->selectRaw('COUNT(*)')
                ->from('follows')
                ->where('follows.follower_id', $user->id)
                ->whereColumn('follows.followed_id', 'users.id'))
            ->when($term === '' && $target instanceof Commit, fn (Builder $query): Builder => $query
                ->orderByDesc(fn (QueryBuilder $query): QueryBuilder => $query
                    ->select('created_at')
                    ->from('follows')
                    ->where('follows.follower_id', $user->id)
                    ->whereColumn('follows.followed_id', 'users.id')))
            ->orderBy('profiles.handle')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
