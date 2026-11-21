<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member of an organization, as its settings list them: the user's public profile, their role and when they joined.
 *
 * @mixin User
 */
final class OrganizationMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $joinedAt = $this->getAttribute('joined_at');
        assert($joinedAt instanceof CarbonInterface);

        return [
            ...new UserResource($this->resource)->toArray($request),
            'role' => $this->getAttribute('role'),
            'joined_at' => $joinedAt->toIso8601ZuluString(),
        ];
    }
}
