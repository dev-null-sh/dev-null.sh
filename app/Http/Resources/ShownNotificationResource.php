<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\Profile;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @implements Arrayable<array-key, mixed>
 */
final class ShownNotificationResource extends JsonResource implements Arrayable
{
    /**
     * @return array<mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $notification = $this->resource;
        assert($notification instanceof Notification);

        return [
            ...$notification->toArray(),
            'actor' => ShownStreams::profile($notification->actor),
            ...$notification->relationLoaded('actors') ? [
                'actors' => $notification->actors->map(fn (Profile $actor): array => ShownStreams::profile($actor))->all(),
            ] : [],
        ];
    }
}
