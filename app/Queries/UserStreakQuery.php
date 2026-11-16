<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type UserStreaks array{longest: int, current: int, shields: int, protected_days: int}
 */
final readonly class UserStreakQuery
{
    /**
     * Days are grouped like the activity graph, by the date of `created_at` in the app timezone. The current streak
     * still counts when the last commit was yesterday, since today is not over yet. Every seven commit days in a
     * streak earn a shield (up to two). A shield automatically covers one completed day without a commit, preserving
     * the streak without increasing its length. Replaying visible days makes reads idempotent and respects moderation.
     *
     * @return UserStreaks
     */
    public function get(User $user, ?User $viewer): array
    {
        /** @var list<string> $days */
        $days = $user->commits()
            ->visibleTo($viewer)
            ->where('created_at', '<', CarbonImmutable::tomorrow())
            ->selectRaw('date(created_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $longest = 0;
        $streak = 0;
        $previous = null;
        $shields = 0;
        $protectedDays = 0;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            $missedDays = $previous instanceof CarbonImmutable ? (int) $previous->diffInDays($date) - 1 : 0;
            $coveredDays = min($missedDays, $shields);
            $shields -= $coveredDays;

            if ($missedDays > $coveredDays) {
                $streak = 0;
                $protectedDays = 0;
            } else {
                $protectedDays += $coveredDays;
            }

            $streak++;

            if ($streak % 7 === 0) {
                $shields = min(2, $shields + 1);
            }

            $longest = max($longest, $streak);
            $previous = $date;
        }

        // Today is still available for committing, so only cover gaps through yesterday.
        $missedDays = $previous instanceof CarbonImmutable ? max(0, (int) $previous->diffInDays(CarbonImmutable::yesterday())) : 0;
        $coveredDays = min($missedDays, $shields);
        $shields -= $coveredDays;
        $isAlive = $previous instanceof CarbonImmutable && $missedDays === $coveredDays;

        return [
            'longest' => $longest,
            'current' => $isAlive ? $streak : 0,
            'shields' => $shields,
            'protected_days' => $isAlive ? $protectedDays + $coveredDays : 0,
        ];
    }
}
