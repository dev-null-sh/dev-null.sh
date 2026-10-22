<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PushAccess;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A one-to-one conversation as the web's thread loads and polls it: the partner, a page of messages, the reactions
 * changed since the version a poll last saw, where each of them has pulled to, whether the partner is typing, and what
 * the composer can do (`access`).
 */
final class ConversationThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{partner: User, messages: Collection<int, Message>, has_older: bool, reactions_version: int, reaction_changes: Collection<int, array{id: int, reactions: EloquentCollection<int, MessageReaction>}>, read_through_id: int, partner_read_through_id: int, partner_read_at: string|null, partner_typing: bool, is_archived: bool, access: PushAccess} $thread */
        $thread = $this->resource;

        return [
            ...$thread,
            'partner' => UserResource::make($thread['partner']),
            'messages' => MessageResource::collection($thread['messages']),
        ];
    }
}
