<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\EventResponse;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class EventAttendeesQuery
{
    public const int LIMIT = 12;

    /**
     * The people going who answered latest, leaving out anyone across a block from the viewer.
     *
     * @return Collection<int, User>
     */
    public function get(Event $event, ?User $viewer): Collection
    {
        return User::query()
            ->select('users.*')
            ->join('event_attendees', 'event_attendees.user_id', '=', 'users.id')
            ->where('event_attendees.event_id', $event->id)
            ->where('event_attendees.response', EventResponse::Going)
            ->when($viewer, fn (Builder $query, User $viewer): Builder => $query->whereNotIn('users.id', $viewer->idsAcrossBlocks()))
            ->orderByDesc('event_attendees.id')
            ->limit(self::LIMIT)
            ->get();
    }
}
