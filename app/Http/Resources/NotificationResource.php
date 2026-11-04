<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use Illuminate\Http\Request;
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
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject_id' => $this->subject_id,
            'reason' => $this->reason,
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'actor' => ProfileResource::make($this->actor),
            'actors' => ProfileResource::collection($this->whenLoaded('actors')),
            'organization' => $this->organization instanceof Organization ? OrganizationResource::make($this->organization) : null,
            'group_id' => $this->group_id,
            'event_count' => $this->event_count,
            'actors_count' => $this->actors_count,
            'unread_count' => $this->unread_count,
            'commit' => $this->commit instanceof Commit ? CommitResource::make($this->commit) : null,
        ];
    }
}
