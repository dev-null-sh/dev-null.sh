<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A one-to-one conversation as the web's thread loads and polls it: the partner, a page of messages, the reactions
 * changed since the version a poll last saw, where each of them has pulled to, whether the partner is typing, and what
 * the composer can do (`access`).
 *
 * @phpstan-import-type DirectThread from ConversationThreadQuery
 *
 * @phpstan-type ThreadWithPartner array{
 *     partner: User,
 *     messages: DirectThread['messages'],
 *     has_older: DirectThread['has_older'],
 *     reactions_version: DirectThread['reactions_version'],
 *     reaction_changes: DirectThread['reaction_changes'],
 *     read_through_id: DirectThread['read_through_id'],
 *     partner_read_through_id: DirectThread['partner_read_through_id'],
 *     partner_read_at: DirectThread['partner_read_at'],
 *     partner_typing: DirectThread['partner_typing'],
 *     is_archived: DirectThread['is_archived'],
 *     pinned_at: DirectThread['pinned_at'],
 *     muted_until_at: DirectThread['muted_until_at'],
 *     access: DirectThread['access']
 * }
 */
final class ConversationThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ThreadWithPartner $thread */
        $thread = $this->resource;

        return [
            ...$thread,
            'partner' => UserResource::make($thread['partner']),
            'messages' => MessageResource::collection($thread['messages']),
        ];
    }
}
