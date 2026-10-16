<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Database\Query\Builder;

/**
 * Subqueries for `whereNotExists` that match when the recipient mutes the actor, or either one blocks the other.
 */
final readonly class SilencedActorQuery
{
    public function __construct(private string $recipientColumn, private string $actorColumn)
    {
        //
    }

    public function mutes(Builder $query): Builder
    {
        return $query->from('mutes')
            ->whereColumn('mutes.muter_id', $this->recipientColumn)
            ->whereColumn('mutes.muted_id', $this->actorColumn);
    }

    public function blocks(Builder $query): Builder
    {
        return $query->from('blocks')
            ->where(fn (Builder $query): Builder => $query
                ->whereColumn('blocks.blocker_id', $this->recipientColumn)
                ->whereColumn('blocks.blocked_id', $this->actorColumn))
            ->orWhere(fn (Builder $query): Builder => $query
                ->whereColumn('blocks.blocker_id', $this->actorColumn)
                ->whereColumn('blocks.blocked_id', $this->recipientColumn));
    }
}
