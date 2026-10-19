<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user: their public profile, plus what only they see on the web's profile settings. That is the lane
 * or custom color they chose (`null` for Automatic) and the one Automatic gives them, their GitHub Sponsors page, and when their profile
 * was last mirrored from GitHub.
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
        return [
            ...UserResource::make($this->resource)->toArray($request),
            'preferred_lane' => $this->preferredLane(),
            'automatic_lane' => $this->automaticLane(),
            'sponsors_url' => $this->has_sponsors_listing ? "https://github.com/sponsors/{$this->username}" : null,
            'github_synced_at' => $this->github_synced_at?->toIso8601ZuluString(),
        ];
    }
}
