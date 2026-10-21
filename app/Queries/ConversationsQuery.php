<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ConversationKind;
use App\Enums\InboxTab;
use App\Enums\OrgRole;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

final readonly class ConversationsQuery
{
    public const int PAGE = 30;

    /**
     * One page of a tab, newest first, continuing before a conversation when scrolling down. The term matches the
     * partner's name or handle, already lowercased and without a leading @.
     *
     * @return array{conversations: Collection<int, array{id: string, kind: ConversationKind, name: string|null, partner: User|null, members: Collection<int, array{user: User, role: OrgRole|null}>|null, last_message: Message|null, last_message_at: string|null, ahead: int, partner_read_through_id: int, partner_read_at: string|null, is_archived: bool}>, has_more: bool}
     */
    public function get(User $user, InboxTab $tab = InboxTab::Open, string $term = '', ?string $before = null): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $cursor = $before === null ? null : ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $before)
            ->first();

        $conversations = Conversation::query()
            ->join('conversation_participants as mine', fn (JoinClause $join): JoinClause => $join
                ->on('mine.conversation_id', '=', 'conversations.id')
                ->where('mine.user_id', $user->id))
            ->leftJoin('conversation_participants as theirs', fn (JoinClause $join): JoinClause => $join
                ->on('theirs.conversation_id', '=', 'conversations.id')
                ->where('theirs.user_id', '!=', $user->id)
                ->where('conversations.kind', ConversationKind::Direct))
            ->where('mine.status', $tab === InboxTab::Fetched ? RemoteStatus::Fetched : RemoteStatus::Open)
            ->when($tab === InboxTab::Open, fn (Builder $query): Builder => $query->whereNull('mine.archived_at'))
            ->when($tab === InboxTab::Stashed, fn (Builder $query): Builder => $query->whereNotNull('mine.archived_at'))
            ->whereNotNull('mine.last_message_at')
            ->where(fn (Builder $query): Builder => $query
                ->where('conversations.kind', ConversationKind::Org)
                ->orWhereNotIn('theirs.user_id', $user->idsAcrossBlocks()))
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereIn('theirs.user_id', $this->matching($escaped))
                ->orWhereRaw("LOWER(conversations.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('conversations.kind', ConversationKind::Org)
                    ->whereIn('conversations.id', ConversationParticipant::query()
                        ->select('conversation_id')
                        ->whereIn('user_id', $this->matching($escaped))))))
            ->when($cursor, fn (Builder $query, ConversationParticipant $cursor): Builder => $query->whereRaw(
                '(mine.last_message_at, conversations.id) < (?, ?)',
                [$cursor->last_message_at, $cursor->conversation_id],
            ))
            ->select('conversations.*')
            ->addSelect([
                'theirs.user_id as partner_id',
                'theirs.status as partner_status',
                'theirs.read_through_id as partner_read_through_id',
                'theirs.read_at as partner_read_at',
                'mine.last_message_at as last_message_at',
                'mine.archived_at as archived_at',
            ])
            ->selectSub(
                Message::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->whereColumn('messages.id', '>', 'mine.read_through_id')
                    ->where('messages.user_id', '!=', $user->id)
                    ->whereNull('messages.event'),
                'ahead',
            )
            ->with(['lastMessage.user', 'lastMessage.subject', 'lastMessage.voice', 'partner', 'participants.user'])
            ->orderByDesc('mine.last_message_at')
            ->orderByDesc('conversations.id')
            ->limit(self::PAGE + 1)
            ->get();

        return [
            'conversations' => $conversations
                ->take(self::PAGE)
                ->map(fn (Conversation $conversation): array => $this->summary($user, $conversation))
                ->toBase(),
            'has_more' => $conversations->count() > self::PAGE,
        ];
    }

    /**
     * Unread messages in open conversations plus fetched requests, for the nav badge.
     */
    public function unreadCount(User $user): int
    {
        $unread = Message::query()
            ->join('conversation_participants as mine', fn (JoinClause $join): JoinClause => $join
                ->on('mine.conversation_id', '=', 'messages.conversation_id')
                ->where('mine.user_id', $user->id)
                ->where('mine.status', RemoteStatus::Open)
                ->whereNull('mine.archived_at'))
            ->whereColumn('messages.id', '>', 'mine.read_through_id')
            ->where('messages.user_id', '!=', $user->id)
            ->whereNull('messages.event')
            ->whereNotIn('messages.user_id', $user->idsAcrossBlocks())
            ->count();

        return $unread + $this->requestCount($user);
    }

    /**
     * Fetched requests waiting on the user, none while requests are turned off.
     */
    public function requestCount(User $user): int
    {
        if (! config('remotes.requests')) {
            return 0;
        }

        return ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->where('status', RemoteStatus::Fetched)
            ->whereNotIn('conversation_id', ConversationParticipant::query()
                ->select('conversation_id')
                ->whereIn('user_id', $user->idsAcrossBlocks()))
            ->count();
    }

    /**
     * @return array{id: string, kind: ConversationKind, name: string|null, partner: User|null, members: Collection<int, array{user: User, role: OrgRole|null}>|null, last_message: Message|null, last_message_at: string|null, ahead: int, partner_read_through_id: int, partner_read_at: string|null, is_archived: bool}
     */
    private function summary(User $user, Conversation $conversation): array
    {
        $partner = $conversation->partner;
        $sharesReads = $partner instanceof User && $user->pull_receipts && $partner->pull_receipts && $conversation->partner_status === RemoteStatus::Open;

        return [
            'id' => $conversation->id,
            'kind' => $conversation->kind,
            'name' => $conversation->name,
            'partner' => $partner,
            'members' => $conversation->isOrg() ? $conversation->participants
                ->sortBy('id')
                ->map(fn (ConversationParticipant $participant): array => ['user' => $participant->user, 'role' => $participant->role])
                ->values()
                ->toBase() : null,
            'last_message' => $conversation->lastMessage,
            'last_message_at' => $conversation->last_message_at?->toJSON(),
            'ahead' => $conversation->ahead,
            'partner_read_through_id' => $sharesReads ? $conversation->partner_read_through_id : 0,
            'partner_read_at' => $sharesReads ? $conversation->partner_read_at?->toJSON() : null,
            'is_archived' => $conversation->archived_at !== null,
        ];
    }

    /**
     * @return Builder<User>
     */
    private function matching(string $escaped): Builder
    {
        return User::query()
            ->select('id')
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(username) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ["%{$escaped}%"]));
    }
}
