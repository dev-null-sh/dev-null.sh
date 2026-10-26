<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Queries\ConversationsQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user: their public profile, plus what only they see on the web's profile settings. That is the lane
 * or custom color they chose (`null` for Automatic) and the one Automatic gives them, their GitHub Sponsors page, and when their profile
 * was last mirrored from GitHub. Their Remotes are `null` while turned off, as the web hides them, and otherwise say who
 * can push to them, whether they share pulls, what the site lets them choose, and the unread count the web's nav shows.
 *
 * @mixin User
 */
final class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            ...UserResource::make($this->resource)->toArray($request),
            'pasteis_mode' => $this->pasteis_mode,
            'pasteis_remaining' => $this->pasteisRemaining(),
            'pasteis_resets_at' => today()->addDay()->toIso8601ZuluString(),
            'pasteis_received' => $this->pasteis_received,
            'preferred_lane' => $this->preferredLane(),
            'automatic_lane' => $this->automaticLane(),
            'sponsors_url' => $this->has_sponsors_listing ? "https://github.com/sponsors/{$this->handle}" : null,
            'github_synced_at' => $this->github_synced_at?->toIso8601ZuluString(),
            'remotes' => $this->remotes_enabled && $user instanceof User ? [
                'from' => $this->remotes_from,
                'pull_receipts' => $this->pull_receipts,
                'requests' => (bool) config('remotes.requests'),
                'everyone' => (bool) config('remotes.everyone'),
                'unread_count' => resolve(ConversationsQuery::class)->unreadCount($user),
            ] : null,
        ];
    }
}
