<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Someone in an org's call, and when they joined it, which decides which of two members offers to the other.
 */
final class CallMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{user: User, joined_at: int} $member */
        $member = $this->resource;

        return [
            'user' => UserResource::make($member['user']),
            'joined_at' => $member['joined_at'],
        ];
    }
}
