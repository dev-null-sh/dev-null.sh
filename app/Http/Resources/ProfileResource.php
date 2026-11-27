<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A profile as its owner shows it, a user or an organization, which its type says, with its ID either way.
 *
 * @mixin Profile
 */
final class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->owner();

        return [
            'type' => $this->type,
            'id' => $this->id,
            ...($owner instanceof User ? UserResource::make($owner) : OrganizationResource::make($owner))->toArray($request),
        ];
    }
}
