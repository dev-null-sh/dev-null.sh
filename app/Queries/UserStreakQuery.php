<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\NotificationType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type UserStreaks array{longest: int, current: int, shields: int, protected_days: int}
 * @phpstan-type ShieldEvent array{type: NotificationType, day: string, balance: int}
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
        return $this->calculate($user, $viewer)['streaks'];
    }

    /**
     * Private owner-visible events for the worker. Profile reads never send notifications.
     *
     * @return list<ShieldEvent>
     */
    public function events(User $user): array
    {
        return $this->calculate($user, $user)['events'];
    }

    /**
     * @return array{streaks: UserStreaks, events: list<ShieldEvent>}
     */
    private function calculate(User $user, ?User $viewer): array
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

        /** @var array<string, bool> $preferences */
        $preferences = DB::table('streak_shield_preferences')->where('user_id', $user->id)
            ->oldest('effective_on')->pluck('enabled', 'effective_on')->all();
        $automatic = $preferences === [] ? $user->automatic_streak_shields : true;
        $today = CarbonImmutable::today()->toDateString();
        $hasToday = in_array($today, $days, true);

        // A sentinel covers completed days before today without pretending today has a commit.
        if (! $hasToday) {
            $days[] = $today;
        }

        $events = [];
        $longest = 0;
        $streak = 0;
        $previous = null;
        $shields = 0;
        $protectedDays = 0;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            // Stop spending as soon as a streak breaks, preserving shields when automatic protection is off.
            for ($missing = $previous?->addDay(); $missing instanceof CarbonImmutable && $missing->lt($date) && $streak > 0; $missing = $missing->addDay()) {
                $missedDay = $missing->toDateString();

                if ($shields > 0 && $this->automaticOn($missedDay, $preferences, $automatic)) {
                    $shields--;
                    $protectedDays++;
                    $events[] = ['type' => NotificationType::StreakShieldUsed, 'day' => $missedDay, 'balance' => $shields];
                } else {
                    $streak = 0;
                    $protectedDays = 0;
                    $events[] = ['type' => NotificationType::StreakEnded, 'day' => $missedDay, 'balance' => $shields];
                }
            }

            if ($day === $today && ! $hasToday) {
                break;
            }

            $streak++;

            if ($streak % 7 === 0 && $shields < 2) {
                $shields++;
                $events[] = ['type' => NotificationType::StreakShieldEarned, 'day' => $day, 'balance' => $shields];
            }

            $longest = max($longest, $streak);
            $previous = $date;
        }

        return [
            'streaks' => ['longest' => $longest, 'current' => $streak, 'shields' => $shields, 'protected_days' => $protectedDays],
            'events' => $events,
        ];
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    private function automaticOn(string $day, array $preferences, bool $automatic): bool
    {
        foreach ($preferences as $effectiveOn => $enabled) {
            if ($effectiveOn > $day) {
                break;
            }

            $automatic = $enabled;
        }

        return $automatic;
    }
}
