<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Enums\ConversationKind;
use App\Enums\RemoteStatus;
use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final readonly class BroadcastsQuery
{
    public const int PAGE = 30;

    /**
     * The directory: every public broadcast that still has its owner, plus the invite-only ones the viewer
     * subscribes to or is invited to, since those are no secret to them. The term matches the broadcast's name or its
     * owner's name or handle, already lowercased and without a leading @.
     *
     * @return array{broadcasts: Collection<int, array{id: string, name: string, visibility: BroadcastVisibility, owner: User, subscriber_count: int, membership: string}>, has_more: bool}
     */
    public function directory(User $viewer, string $term = '', ?string $before = null): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $cursor = $before === null ? null : $this->listed($viewer)
            ->withoutEagerLoads()->select(['conversations.id', 'conversations.created_at'])
            ->whereKey($before)->first();

        $broadcasts = $this->listed($viewer)
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereRaw("LOWER(conversations.name) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                ->orWhereIn('conversations.id', ConversationParticipant::query()
                    ->select('conversation_id')
                    ->where('role', BroadcastRole::Owner)
                    ->whereIn('user_id', $this->matching($escaped)))))
            ->when($cursor, fn (Builder $query, Conversation $cursor): Builder => $query->whereRaw(
                '(conversations.created_at, conversations.id) < (?, ?)',
                [$cursor->created_at, $cursor->id],
            ))
            ->latest('conversations.created_at')
            ->orderByDesc('conversations.id')
            ->limit(self::PAGE + 1)
            ->get();

        return [
            'broadcasts' => $broadcasts->take(self::PAGE)
                ->map(fn (Conversation $broadcast): array => $this->summaryOf($viewer, $broadcast))
                ->toBase(),
            'has_more' => $broadcasts->count() > self::PAGE,
        ];
    }

    /**
     * The latest page of public broadcasts an owner runs, for their profile. Names and sizes only: no members, no
     * messages, nothing of the invite-only ones. Further pages live in the directory.
     *
     * @return Collection<int, array{id: string, name: string, subscriber_count: int}>
     */
    public function of(User $owner): Collection
    {
        return Conversation::query()
            ->where('kind', ConversationKind::Broadcast)
            ->where('visibility', BroadcastVisibility::Public)
            ->whereIn('id', ConversationParticipant::query()
                ->select('conversation_id')
                ->where('user_id', $owner->id)
                ->where('role', BroadcastRole::Owner))
            ->withCount(['participants as subscriber_count' => fn (Builder $query): Builder => $query->where('status', RemoteStatus::Open)])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::PAGE)
            ->get()
            ->map(fn (Conversation $broadcast): array => ['id' => $broadcast->id, 'name' => $broadcast->name ?? '', 'subscriber_count' => $broadcast->subscriber_count])
            ->toBase();
    }

    /**
     * What a profile page, the directory, or an invitee deciding on an invitation may know about a broadcast: its
     * name, who runs it, how many subscribe, and where the viewer stands with it.
     *
     * @return array{id: string, name: string, visibility: BroadcastVisibility, owner: User, subscriber_count: int, membership: string}
     */
    public function summary(User $viewer, Conversation $broadcast): array
    {
        return $this->summaryOf($viewer, $this->listed($viewer)->whereKey($broadcast->id)->firstOrFail());
    }

    public function isInvited(User $viewer, Conversation $broadcast): bool
    {
        return BroadcastInvitation::query()->where('conversation_id', $broadcast->id)->where('user_id', $viewer->id)->exists();
    }

    /**
     * @return array{id: string, name: string, visibility: BroadcastVisibility, owner: User, subscriber_count: int, membership: string}
     */
    private function summaryOf(User $viewer, Conversation $broadcast): array
    {
        $mine = $broadcast->participants->firstWhere('user_id', $viewer->id);
        $membership = $mine?->status === RemoteStatus::Open ? $mine->broadcastRole()?->value : null;

        return [
            'id' => $broadcast->id,
            'name' => $broadcast->name ?? '',
            'visibility' => $broadcast->visibility ?? BroadcastVisibility::Invite,
            'owner' => $broadcast->participants->firstOrFail(fn (ConversationParticipant $participant): bool => $participant->role === BroadcastRole::Owner)->user,
            'subscriber_count' => $broadcast->subscriber_count,
            'membership' => $membership ?? ($broadcast->is_invited ? 'invited' : 'none'),
        ];
    }

    /**
     * @return Builder<Conversation>
     */
    private function listed(User $viewer): Builder
    {
        return Conversation::query()
            ->withCount(['participants as subscriber_count' => fn (Builder $query): Builder => $query->where('status', RemoteStatus::Open)])
            ->withExists(['invitations as is_invited' => fn (Builder $query): Builder => $query->where('user_id', $viewer->id)])
            ->with(['participants' => fn (HasMany $query): HasMany => $query
                ->where(fn (Builder $query): Builder => $query->where('user_id', $viewer->id)->orWhere('role', BroadcastRole::Owner))
                ->with('user')])
            ->where('kind', ConversationKind::Broadcast)
            ->whereIn('id', ConversationParticipant::query()
                ->select('conversation_id')
                ->where('role', BroadcastRole::Owner))
            ->where(fn (Builder $query): Builder => $query
                ->where('visibility', BroadcastVisibility::Public)
                ->orWhereIn('id', ConversationParticipant::query()
                    ->select('conversation_id')
                    ->where('user_id', $viewer->id)
                    ->where('status', RemoteStatus::Open))
                ->orWhereIn('id', BroadcastInvitation::query()
                    ->select('conversation_id')
                    ->where('user_id', $viewer->id)));
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
