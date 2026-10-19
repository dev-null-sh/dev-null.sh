<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\MessageReaction;
use App\Models\User;

final readonly class ReactionUsageQuery
{
    public const int LIMIT = 32;

    /**
     * The emoji the user reacts with, most used first, for ranking the reaction picker.
     *
     * @return array<int, array{emoji: string, count: int}>
     */
    public function get(User $user): array
    {
        return MessageReaction::query()
            ->where('user_id', $user->id)
            ->selectRaw('emoji, count(*) as uses')
            ->groupBy('emoji')
            ->orderByDesc('uses')
            ->orderBy('emoji')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (MessageReaction $reaction): array => ['emoji' => $reaction->emoji, 'count' => $reaction->uses])
            ->values()
            ->all();
    }
}
