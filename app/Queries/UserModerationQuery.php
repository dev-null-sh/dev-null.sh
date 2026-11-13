<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ModerationReason;
use App\Enums\TimeoutDuration;
use App\Models\Ban;
use App\Models\BanEvent;
use App\Models\User;
use App\Models\UserTimeout;
use Carbon\CarbonInterface;

/**
 * @phpstan-type UserModeration array{timeoutsCount: int, timeout: array{reason: string, endsAt: CarbonInterface}|null, ban: array{reason: string, bannedAt: CarbonInterface}|null, reasons: array<int|string, string>, durations: array<int|string, string>}
 * @phpstan-type UserModerationHistory array{timeoutsCount: int, bansCount: int, timeouts: list<array{reason: string, duration: string, startedAt: CarbonInterface, endsAt: CarbonInterface}>, bans: list<array{reason: string, bannedAt: CarbonInterface}>}
 */
final readonly class UserModerationQuery
{
    /**
     * What the account owner can see about their own moderation history.
     *
     * @return UserModerationHistory
     */
    public function history(User $user): array
    {
        $timeouts = $user->timeouts()->latest()->get();
        $bans = $user->banEvents()->latest()->get();

        return [
            'timeoutsCount' => $timeouts->count(),
            'bansCount' => $bans->count(),
            'timeouts' => array_values($timeouts->map(fn (UserTimeout $timeout): array => [
                'reason' => $timeout->reason->label(),
                'duration' => $timeout->duration(),
                'startedAt' => $timeout->created_at,
                'endsAt' => $timeout->ends_at,
            ])->all()),
            'bans' => array_values($bans->map(fn (BanEvent $ban): array => [
                'reason' => $ban->reason->label(),
                'bannedAt' => $ban->created_at,
            ])->all()),
        ];
    }

    /**
     * What admins see of each user's timeouts and ban, and what they can choose from to give another, keyed by user id.
     * Timeouts and bans go by GitHub account, so they are read by GitHub id, in two queries whatever the number of users.
     *
     * @param  iterable<int, User>  $users
     * @return array<string, UserModeration>
     */
    public function get(iterable $users): array
    {
        $users = collect($users);
        $githubIds = $users->pluck('github_id')->all();

        $timeouts = UserTimeout::query()->whereIn('github_id', $githubIds)->latest('ends_at')->get();
        $counts = $timeouts->countBy('github_id');
        $active = $timeouts->where('ends_at', '>', now())->unique('github_id')->keyBy('github_id');

        $bans = Ban::query()->whereIn('github_id', $githubIds)->get()->keyBy('github_id');

        return $users->mapWithKeys(function (User $user) use ($counts, $active, $bans): array {
            $timeout = $active->get($user->github_id);
            $ban = $bans->get($user->github_id);

            return [$user->id => [
                'timeoutsCount' => $counts->get($user->github_id, 0),
                'timeout' => $timeout instanceof UserTimeout ? ['reason' => $timeout->reason->label(), 'endsAt' => $timeout->ends_at] : null,
                'ban' => $ban instanceof Ban ? ['reason' => $ban->reason->label(), 'bannedAt' => $ban->created_at] : null,
                'reasons' => ModerationReason::options(),
                'durations' => TimeoutDuration::options(),
            ]];
        })->all();
    }
}
