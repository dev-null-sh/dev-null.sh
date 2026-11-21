<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A group of notifications, as the web shows it: the newest one, the group it leads, how many events and actors the
 * group has and how many events are unread, and in a list the first few actors. An actor is a user or an
 * organization, as its type says, and a user actor says whether the user follows them. A rejected commit comes with
 * the reason the moderator gave. A member hears about an organization's commits and mentions with the organization.
 *
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    private ?GuildBadges $guildBadges = null;

    public static function newCollection(mixed $resource): GuildMentionCollection
    {
        return new GuildMentionCollection($resource, self::class, self::guildBadges(...));
    }

    /**
     * @param  iterable<array-key, mixed>  $notifications
     */
    public static function guildBadges(iterable $notifications, ?User $viewer): GuildBadges
    {
        return GuildBadges::forCommits(collect($notifications)->map(fn (mixed $notification): ?Commit => $notification instanceof Notification && $notification->relationLoaded('commit') ? $notification->commit : null), $viewer);
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
        $badges = $this->guildBadges ?? self::guildBadges([$this->resource], $request->user());

        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject_id' => $this->subject_id,
            'reason' => $this->reason,
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'actor' => ProfileResource::make($this->actor),
            'actors' => $this->whenLoaded('actors', fn (): AnonymousResourceCollection => ProfileResource::collection($this->actors)),
            'organization' => $this->organization instanceof Organization ? OrganizationResource::make($this->organization) : null,
            'group_id' => $this->group_id,
            'event_count' => $this->event_count,
            'actors_count' => $this->actors_count,
            'unread_count' => $this->unread_count,
            'commit' => $this->commit instanceof Commit ? CommitResource::make($this->commit)->withGuildBadges($badges)->resolve($request) : null,
            ...$this->type === NotificationType::TimedOut && $this->timeout instanceof UserTimeout ? ['timeout' => [
                'reason' => $this->timeout->reason->label(),
                'duration' => $this->timeout->duration(),
                'ends_at' => $this->timeout->ends_at->toIso8601ZuluString(),
            ]] : [],
        ];
    }
}
