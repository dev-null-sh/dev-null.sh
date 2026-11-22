<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContributorRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContributorRequest */
final class ContributorRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id, 'project_url' => $this->project_url, 'kind' => $this->kind,
            'status' => $this->status, 'filled_at' => $this->filled_at?->toIso8601ZuluString(),
            'can_manage' => $viewer instanceof User && $this->user_id === $viewer->id,
            'commit' => ShownStreams::commit($this->commit),
        ];
    }
}
