<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the web's inbox: the partner, or an org's name and members, the last message, how many messages from
 * others the user hasn't pulled yet (`ahead`), the id of the newest message from others, read or not and never an
 * org event (`last_incoming_message_id`), how far the partner has pulled when both of them share pulls, whether the
 * user stashed or pinned it, and until when they muted it.
 *
 * @phpstan-import-type ConversationSummary from ConversationsQuery
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationSummary $conversation */
        $conversation = $this->resource;

        return [
            ...$conversation,
            'partner' => $conversation['partner'] instanceof User ? UserResource::make($conversation['partner']) : null,
            'members' => $conversation['members']?->map(fn (array $member): array => [
                'user' => UserResource::make($member['user']),
                'role' => $member['role'],
            ]),
            'last_message' => $conversation['last_message'] instanceof Message ? MessageResource::make($conversation['last_message']) : null,
        ];
    }
}
