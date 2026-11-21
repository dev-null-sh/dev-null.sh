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
    private ?GuildBadges $guildBadges = null;

    public static function newCollection(mixed $resource): GuildMentionCollection
    {
        return new GuildMentionCollection($resource, self::class, self::guildBadges(...));
    }

    /**
     * @param  iterable<array-key, mixed>  $conversations
     */
    public static function guildBadges(iterable $conversations, ?User $viewer): GuildBadges
    {
        return GuildBadges::forMessages(collect($conversations)->map(fn (mixed $conversation): ?Message => is_array($conversation) && ($conversation['last_message'] ?? null) instanceof Message ? $conversation['last_message'] : null), $viewer);
    }

    public function withGuildBadges(GuildBadges $badges): self
    {
        $resource = clone $this;
        $resource->guildBadges = $badges;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationSummary $conversation */
        $conversation = $this->resource;

        $badges = $this->guildBadges ?? self::guildBadges([$conversation], $request->user());

        return [
            ...$conversation,
            'partner' => $conversation['partner'] instanceof User ? UserResource::make($conversation['partner']) : null,
            'members' => $conversation['members']?->map(fn (array $member): array => [
                'user' => UserResource::make($member['user']),
                'role' => $member['role'],
            ]),
            'last_message' => $conversation['last_message'] instanceof Message ? MessageResource::make($conversation['last_message'])->withGuildBadges($badges) : null,
        ];
    }
}
