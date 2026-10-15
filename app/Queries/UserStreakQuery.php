<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UserStreakQuery
{
    /**
     * Days are grouped like the activity graph, by the date of `created_at` in the app timezone. The current streak
     * still counts when the last commit was yesterday, since today is not over yet.
     *
     * @return array{longest: int, current: int}
     */
    public function get(User $user, User $viewer): array
    {
        /** @var list<string> $days */
        $days = $user->commits()
            ->visibleTo($viewer)
            ->selectRaw('date(created_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $longest = 0;
        $streak = 0;
        $previous = null;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            $streak = $previous?->addDay()->isSameDay($date) ? $streak + 1 : 1;
            $longest = max($longest, $streak);
            $previous = $date;
        }

        $isAlive = $previous?->greaterThanOrEqualTo(CarbonImmutable::yesterday()) ?? false;

        return [
            'longest' => $longest,
            'current' => $isAlive ? $streak : 0,
        ];
    }
}
