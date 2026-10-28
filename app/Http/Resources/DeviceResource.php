<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A device the user approved, as the web's Devices page shows it: its name, the fingerprint of its key, and when it was
 * approved and last used. Never the key itself, or anything that signs a request.
 *
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fingerprint' => $this->fingerprint,
            'approved_at' => $this->approved_at->toIso8601ZuluString(),
            'last_used_at' => $this->last_used_at?->toIso8601ZuluString(),
        ];
    }
}
