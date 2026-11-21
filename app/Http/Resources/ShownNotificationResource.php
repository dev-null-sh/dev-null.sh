<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\UserTimeout;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @implements Arrayable<array-key, mixed>
 */
final class ShownNotificationResource extends JsonResource implements Arrayable
{
    public function __construct(Notification $resource, private readonly GuildBadges $badges)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $notification = $this->resource;
        assert($notification instanceof Notification);

        $shown = $notification->toArray();

        return [
            ...$shown,
            ...is_array($shown['commit'] ?? null) ? ['commit' => $this->badges->onCommit($shown['commit'])] : [],
            'actor' => ShownStreams::profile($notification->actor),
            ...$notification->type === NotificationType::TimedOut && $notification->relationLoaded('timeout') && $notification->timeout instanceof UserTimeout ? [
                'timeout' => [
                    'reason' => $notification->timeout->reason->label(),
                    'duration' => $notification->timeout->duration(),
                    'ends_at' => $notification->timeout->ends_at->toIso8601ZuluString(),
                ],
            ] : [],
            ...$notification->relationLoaded('actors') ? [
                'actors' => $notification->actors->map(fn (Profile $actor): array => ShownStreams::profile($actor))->all(),
            ] : [],
        ];
    }
}
