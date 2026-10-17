<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\UpdateConversationTyping;
use App\Enums\OrgRole;
use App\Enums\PushAccess;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class ConversationThreadQuery
{
    public const int PAGE = 50;

    /**
     * A one-to-one conversation. Reads only show as pulled when both people share them, and only once the partner
     * has merged the conversation.
     *
     * @return array{messages: Collection<int, Message>, has_older: bool, read_through_id: int, partner_read_through_id: int, partner_read_at: string|null, partner_typing: bool, is_archived: bool, access: PushAccess}
     */
    public function get(User $user, User $partner, ?int $after = null, ?int $before = null): array
    {
        $conversation = Conversation::between($user, $partner);

        $participants = $conversation instanceof Conversation
            ? $conversation->participants()->get()->keyBy('user_id')
            : new Collection();
        $mine = $participants->get($user->id);
        $theirs = $participants->get($partner->id);
        $access = PushAccessQuery::of($user, $partner, $mine, $theirs);
        $sharesReads = $user->pull_receipts && $partner->pull_receipts && $theirs?->status === RemoteStatus::Open;
        [$messages, $hasOlder] = $conversation instanceof Conversation ? $this->page($conversation, 0, $after, $before) : [new Collection(), false];

        return [
            'messages' => $messages,
            'has_older' => $hasOlder,
            'read_through_id' => $mine->read_through_id ?? 0,
            'partner_read_through_id' => $sharesReads ? $theirs->read_through_id : 0,
            'partner_read_at' => $sharesReads ? $theirs->read_at?->toJSON() : null,
            'partner_typing' => $access === PushAccess::Open && $conversation instanceof Conversation && Cache::has(UpdateConversationTyping::key($partner, $conversation)),
            'is_archived' => $mine?->archived_at !== null,
            'access' => $access,
        ];
    }

    /**
     * An org the user is in, from where their history starts. Each member's reads show only when both share pulls.
     *
     * @return array{messages: Collection<int, Message>, has_older: bool, read_through_id: int, members: Collection<int, array{user: User, role: OrgRole|null, read_through_id: int}>, typing: array<int, string>, is_archived: bool, role: OrgRole|null, access: PushAccess}
     */
    public function org(User $user, Conversation $org, ?int $after = null, ?int $before = null): array
    {
        $participants = $org->participants()->with('user')->oldest('id')->get();
        $mine = $participants->firstOrFail(fn (ConversationParticipant $participant): bool => $participant->user_id === $user->id);
        [$messages, $hasOlder] = $this->page($org, $mine->visible_from_id, $after, $before, withPeople: true);

        return [
            'messages' => $messages,
            'has_older' => $hasOlder,
            'read_through_id' => $mine->read_through_id,
            'members' => $participants->map(fn (ConversationParticipant $participant): array => [
                'user' => $participant->user,
                'role' => $participant->role,
                'read_through_id' => $user->pull_receipts && $participant->user->pull_receipts ? $participant->read_through_id : 0,
            ])->values()->toBase(),
            'typing' => $participants
                ->reject(fn (ConversationParticipant $participant): bool => $participant->user_id === $user->id)
                ->filter(fn (ConversationParticipant $participant): bool => Cache::has(UpdateConversationTyping::key($participant->user, $org)))
                ->map(fn (ConversationParticipant $participant): string => $participant->user->username)
                ->values()
                ->all(),
            'is_archived' => $mine->archived_at !== null,
            'role' => $mine->role,
            'access' => PushAccess::Open,
        ];
    }

    /**
     * The latest page, the page before a message for scrolling back, or everything after one for polling.
     *
     * @return array{Collection<int, Message>, bool}
     */
    private function page(Conversation $conversation, int $visibleFrom, ?int $after, ?int $before, bool $withPeople = false): array
    {
        $query = $conversation->messages()
            ->where('id', '>', $visibleFrom)
            ->with($withPeople ? ['user', 'subject'] : []);

        $messages = $after === null
            ? $query->where('id', '<', $before ?? PHP_INT_MAX)->orderByDesc('id')->limit(self::PAGE + 1)->get()->reverse()->values()
            : $query->where('id', '>', $after)->orderBy('id')->limit(self::PAGE * 2)->get();

        $hasOlder = $after === null && $messages->count() > self::PAGE;

        return [($hasOlder ? $messages->slice(1)->values() : $messages)->toBase(), $hasOlder];
    }
}
