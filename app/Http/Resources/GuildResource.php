<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A guild as the web's Guilds page shows it, with whether the user joined or muted it. A guild without a moderator is
 * paused.
 *
 * On API routes, icon_url is signed for the user, as the app has no web session, and stays the same all day so the app
 * can cache it.
 *
 * @mixin Guild
 */
final class GuildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'icon_url' => $request->routeIs('api-*') ? $this->apiIconUrl($request->user()) : $this->iconUrl(),
            'member_count' => $this->members_count,
            'is_joined' => $this->is_joined,
            'is_muted' => $this->is_muted,
            'is_paused' => $this->user_id === null,
            'html_url' => route('guild.show', $this->handle),
        ];
    }
}
