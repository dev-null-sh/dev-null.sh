<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Commit;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Hides what banned users committed and pushed, and the orgs they're in, everywhere, until the ban is lifted. Commits
 * whose author deleted their account have no user, so they stay.
 *
 * @implements Scope<Commit|Message|ConversationParticipant>
 */
final readonly class WithoutBannedAuthors implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(fn (Builder $query): Builder => $query
            ->whereNull($builder->qualifyColumn('user_id'))
            ->orWhereNotIn($builder->qualifyColumn('user_id'), fn (QueryBuilder $users): QueryBuilder => $users
                ->select('id')
                ->from('users')
                ->whereIn('github_id', fn (QueryBuilder $bans): QueryBuilder => $bans->select('github_id')->from('bans'))));
    }
}
