<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BroadcastRole;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class BroadcastMembersQuery
{
    public const int PAGE = 30;

    /**
     * Only the owner sees the subscriber roster and bans. Departed members are not listed; banned rows remain so
     * the owner can unban them. The retained participant row also preserves the original history cutoff.
     *
     * @return array{members: Collection<int, array{user: User, role: BroadcastRole|null, is_banned: bool}>, next_cursor: int|null}
     */
    public function get(Conversation $broadcast, string $term = '', int $after = 0): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $members = $broadcast->participants()
            ->whereIn('status', [RemoteStatus::Open, RemoteStatus::Banned])
            ->where('id', '>', $after)
            ->when($term !== '', fn (Builder $query): Builder => $query->whereHas('user', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                    ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))))
            ->with('user')
            ->orderBy('id')
            ->limit(self::PAGE + 1)
            ->get();
        $page = $members->take(self::PAGE);

        return [
            'members' => $page->map(fn (ConversationParticipant $participant): array => [
                'user' => $participant->user,
                'role' => $participant->broadcastRole(),
                'is_banned' => $participant->status === RemoteStatus::Banned,
            ])->values()->toBase(),
            'next_cursor' => $members->count() > self::PAGE ? $page->last()?->id : null,
        ];
    }
}
