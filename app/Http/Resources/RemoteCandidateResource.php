<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Someone the user can pick for a new remote or an org, with the reason they can't be picked, if there is one, as the
 * web's pickers show it.
 */
final class RemoteCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{user: User, reason: string|null} $candidate */
        $candidate = $this->resource;

        return [
            'user' => UserResource::make($candidate['user']),
            'reason' => $candidate['reason'],
        ];
    }
}
