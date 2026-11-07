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
     * @param  array{stars_count: int, starred: bool}|null  $commitStars  how starred the commit is, when the inbox lets the user star it
     */
    public function __construct(Notification $notification, private readonly ?array $commitStars = null)
    {
        parent::__construct($notification);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $notification = $this->resource;
        assert($notification instanceof Notification);
        $shown = $notification->toArray();
        $commit = $shown['commit'] ?? null;

        return [
            ...$shown,
            ...is_array($commit) && $this->commitStars !== null ? ['commit' => [...$commit, ...$this->commitStars]] : [],
            'actor' => ShownStreams::profile($notification->actor),
            ...$notification->relationLoaded('actors') ? [
                'actors' => $notification->actors->map(fn (Profile $actor): array => ShownStreams::profile($actor))->all(),
            ] : [],
        ];
    }
}
