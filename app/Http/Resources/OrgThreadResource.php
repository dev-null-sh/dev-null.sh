<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OrgRole;
use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * An org as the web's thread loads and polls it: its name, a page of messages from where the user's history starts,
 * the reactions changed since the version a poll last saw, its members with their roles and, where both share pulls,
 * how far each has pulled, who is typing, and the user's own role.
 */
final class OrgThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{org: Conversation, messages: Collection<int, Message>, has_older: bool, reactions_version: int, reaction_changes: Collection<int, array{id: int, reactions: EloquentCollection<int, MessageReaction>}>, read_through_id: int, members: Collection<int, array{user: User, role: OrgRole|null, read_through_id: int, suggests_mentions: bool, is_followed: bool}>, typing: array<int, string>, is_archived: bool, role: OrgRole|null, access: PushAccess} $thread */
        $thread = $this->resource;

        return [
            ...$thread,
            'org' => ['id' => $thread['org']->id, 'name' => $thread['org']->name],
            'messages' => MessageResource::collection($thread['messages']),
            'members' => $thread['members']->map(fn (array $member): array => [
                'role' => $member['role'],
                'read_through_id' => $member['read_through_id'],
                'user' => UserResource::make($member['user']),
            ]),
        ];
    }
}
