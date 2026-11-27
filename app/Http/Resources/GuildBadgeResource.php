<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guild */
final class GuildBadgeResource extends JsonResource
{
    /**
     * @return array{handle: string, name: string, emoji: string, icon_url: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'emoji' => $this->emoji,
            'icon_url' => $request->routeIs('api-*') ? $this->apiIconUrl($request->user()) : $this->iconUrl(),
        ];
    }
}
