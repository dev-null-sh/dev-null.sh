<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ConversationKind;
use App\Enums\OrgRole;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A row of the web's inbox: the partner, or an org's name and members, the last message, how many messages from
 * others the user hasn't pulled yet (`ahead`), how far the partner has pulled when both of them share pulls, and
 * whether the user stashed it.
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{id: string, kind: ConversationKind, name: string|null, partner: User|null, members: Collection<int, array{user: User, role: OrgRole|null}>|null, last_message: Message|null, last_message_at: string|null, ahead: int, partner_read_through_id: int, partner_read_at: string|null, is_archived: bool} $conversation */
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
