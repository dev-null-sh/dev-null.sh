<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\UserModerationQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's own timeouts and bans, newest first, as their profile shows them.
 *
 * @phpstan-import-type UserModerationHistory from UserModerationQuery
 *
 * @property UserModerationHistory $resource
 */
final class ModerationHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'timeouts_count' => $this->resource['timeoutsCount'],
            'bans_count' => $this->resource['bansCount'],
            'timeouts' => array_map(fn (array $timeout): array => [
                'id' => $timeout['id'],
                'reason' => $timeout['reason'],
                'duration' => $timeout['duration'],
                'started_at' => $timeout['startedAt']->toIso8601ZuluString(),
                'ends_at' => $timeout['endsAt']->toIso8601ZuluString(),
            ], $this->resource['timeouts']),
            'bans' => array_map(fn (array $ban): array => [
                'id' => $ban['id'],
                'reason' => $ban['reason'],
                'banned_at' => $ban['bannedAt']->toIso8601ZuluString(),
            ], $this->resource['bans']),
        ];
    }
}
