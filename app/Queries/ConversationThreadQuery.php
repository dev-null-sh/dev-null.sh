<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\UpdateConversationTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class ConversationThreadQuery
{
    public const int PAGE = 50;

    /**
     * The latest page, the page before a message for scrolling back, or everything after one for polling.
     *
     * @return array{messages: Collection<int, Message>, has_older: bool, read_through_id: int, partner_read_through_id: int, partner_read_at: string|null, partner_typing: bool, is_archived: bool}
     */
    public function get(User $user, User $partner, ?int $after = null, ?int $before = null): array
    {
        $conversation = Conversation::query()->where('pair', Conversation::pairOf($user, $partner))->first();
        $typing = Cache::has(UpdateConversationTyping::key($partner, $user));

        if (! $conversation instanceof Conversation) {
            return [
                'messages' => new Collection(),
                'has_older' => false,
                'read_through_id' => 0,
                'partner_read_through_id' => 0,
                'partner_read_at' => null,
                'partner_typing' => $typing,
                'is_archived' => false,
            ];
        }

        $participants = $conversation->participants()
            ->get()
            ->keyBy('user_id');

        $messages = $after === null
            ? $conversation->messages()
                ->where('id', '<', $before ?? PHP_INT_MAX)
                ->orderByDesc('id')
                ->limit(self::PAGE + 1)
                ->get()
                ->reverse()
                ->values()
            : $conversation->messages()
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit(self::PAGE * 2)
                ->get();

        $hasOlder = $after === null && $messages->count() > self::PAGE;

        return [
            'messages' => ($hasOlder ? $messages->slice(1)->values() : $messages)->toBase(),
            'has_older' => $hasOlder,
            'read_through_id' => $participants->get($user->id)->read_through_id ?? 0,
            'partner_read_through_id' => $participants->get($partner->id)->read_through_id ?? 0,
            'partner_read_at' => $participants->get($partner->id)?->read_at?->toJSON(),
            'partner_typing' => $typing,
            'is_archived' => $participants->get($user->id)?->archived_at !== null,
        ];
    }
}
