<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

final readonly class ConversationsQuery
{
    /**
     * The term matches the partner's name or handle, already lowercased and without a leading @.
     *
     * @return Collection<int, array{id: int, partner: User, last_message: Message|null, last_message_at: string|null, ahead: int, partner_read_through_id: int, partner_read_at: string|null, is_archived: bool}>
     */
    public function get(User $user, bool $archived = false, string $term = '', int $limit = 50): Collection
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        $conversations = Conversation::query()
            ->join('conversation_participants as mine', fn (JoinClause $join): JoinClause => $join
                ->on('mine.conversation_id', '=', 'conversations.id')
                ->where('mine.user_id', $user->id))
            ->join('conversation_participants as theirs', fn (JoinClause $join): JoinClause => $join
                ->on('theirs.conversation_id', '=', 'conversations.id')
                ->where('theirs.user_id', '!=', $user->id))
            ->whereNotNull('conversations.last_message_id')
            ->whereNotIn('theirs.user_id', $user->idsAcrossBlocks())
            ->when($archived, fn (Builder $query): Builder => $query->whereNotNull('mine.archived_at'), fn (Builder $query): Builder => $query->whereNull('mine.archived_at'))
            ->when($term !== '', fn (Builder $query): Builder => $query->whereIn('theirs.user_id', User::query()
                ->select('id')
                ->where(fn (Builder $query): Builder => $query
                    ->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                    ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]))))
            ->select('conversations.*')
            ->addSelect([
                'theirs.user_id as partner_id',
                'theirs.read_through_id as partner_read_through_id',
                'theirs.read_at as partner_read_at',
                'mine.archived_at as archived_at',
            ])
            ->selectSub(
                Message::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->whereColumn('messages.id', '>', 'mine.read_through_id')
                    ->where('messages.user_id', '!=', $user->id),
                'ahead',
            )
            ->with(['lastMessage', 'partner'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id')
            ->limit($limit)
            ->get();

        return $conversations
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'partner' => $conversation->partner,
                'last_message' => $conversation->lastMessage,
                'last_message_at' => $conversation->last_message_at?->toJSON(),
                'ahead' => $conversation->ahead,
                'partner_read_through_id' => $conversation->partner_read_through_id,
                'partner_read_at' => $conversation->partner_read_at?->toJSON(),
                'is_archived' => $conversation->archived_at !== null,
            ])
            ->toBase();
    }

    /**
     * Messages waiting across every conversation still in the inbox, for the nav badge.
     */
    public function unreadCount(User $user): int
    {
        return Message::query()
            ->join('conversation_participants as mine', fn (JoinClause $join): JoinClause => $join
                ->on('mine.conversation_id', '=', 'messages.conversation_id')
                ->where('mine.user_id', $user->id)
                ->whereNull('mine.archived_at'))
            ->whereColumn('messages.id', '>', 'mine.read_through_id')
            ->where('messages.user_id', '!=', $user->id)
            ->whereNotIn('messages.user_id', $user->idsAcrossBlocks())
            ->count();
    }
}
