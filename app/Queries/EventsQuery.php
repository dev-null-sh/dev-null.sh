<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\EventResponse;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class EventsQuery
{
    public const int PER_PAGE = 20;

    /**
     * Upcoming events, soonest first, until they end; past and cancelled ones, latest first. Muted hosts are left out
     * here, as they would be from a log, but their events stay on their profile.
     *
     * @return CursorPaginator<int, Event>
     */
    public function paginate(?User $viewer, bool $past = false): CursorPaginator
    {
        return $this->builder($viewer)
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->withoutMutedAuthorsFor($viewer))
            ->when(
                $past,
                fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query->where('ends_at', '<=', now())->orWhereNotNull('cancelled_at'))
                    ->latest('starts_at')
                    ->orderByDesc('id'),
                fn (Builder $query): Builder => $query
                    ->where('ends_at', '>', now())
                    ->whereNull('cancelled_at')
                    ->oldest('starts_at')
                    ->orderBy('id'),
            )
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The events a user hosts, as the viewer may see them, latest first.
     *
     * @return CursorPaginator<int, Event>
     */
    public function paginateHostedBy(User $host, ?User $viewer): CursorPaginator
    {
        return $this->builder($viewer)
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->whereBelongsTo($host))
            ->latest('starts_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return Builder<Event>
     */
    private function builder(?User $viewer): Builder
    {
        return Event::query()
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer))
            ->with('host')
            ->withCount(['attendees as going_count' => fn (Builder $query): Builder => $query->where('response', EventResponse::Going)]);
    }
}
