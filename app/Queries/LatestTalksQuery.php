<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Talk;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class LatestTalksQuery
{
    /**
     * Recent published talks anyone can browse from the aside explorer.
     *
     * @return Collection<int, array{id: string, title: string, event_name: string|null, presented_on: string, user: array{id: string, username: string, name: string, avatar_url: string|null, lane: int|null}}>
     */
    public function get(?User $viewer, int $limit = 5): Collection
    {
        return Talk::query()
            ->whereNotNull('published_at')
            ->where('is_unlisted', false)
            ->whereNotNull('commit_id')
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($viewer)->approved())
            ->with(['user:id,username,name,avatar_url,preferred_lane,lane_color'])
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Talk $talk): array => [
                'id' => $talk->id,
                'title' => $talk->title,
                'event_name' => $talk->event_name,
                'presented_on' => $talk->presented_on->toDateString(),
                'user' => [
                    'id' => $talk->user->id,
                    'username' => $talk->user->username,
                    'name' => $talk->user->name,
                    'avatar_url' => $talk->user->avatar_url,
                    'lane' => $talk->user->lane(),
                ],
            ]);
    }
}
