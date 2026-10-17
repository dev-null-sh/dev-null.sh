<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user: their public profile, plus their pastéis. That is whether pastéis mode is on, how many pastéis
 * they may still give today and when the allowance comes back, at 00:00 UTC as on the web, and how many others gave
 * them, which they get even while the mode is off.
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
            'pasteis_mode' => $this->pasteis_mode,
            'pasteis_remaining' => $this->pasteisRemaining(),
            'pasteis_resets_at' => today()->addDay()->toIso8601ZuluString(),
            'pasteis_received' => $this->pasteis_received,
        ];
    }
}
