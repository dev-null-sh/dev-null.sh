<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Achievement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UserAchievementsQuery
{
    public function __construct(private UserStreakQuery $streaks)
    {
        //
    }

    /**
     * @return array{
     *     level: int,
     *     rank: string,
     *     xp: int,
     *     next_level_xp: ?int,
     *     level_progress: int,
     *     xp_breakdown: array{
     *         commits: array{count: int, multiplier: int, xp: int},
     *         streak: array{count: int, multiplier: int, xp: int},
     *         stars_received: array{count: int, multiplier: int, xp: int},
     *         replies: array{count: int, multiplier: int, xp: int},
     *         stars_given: array{count: int, multiplier: int, xp: int}
     *     },
     *     ranks: list<array{
     *         rank: string,
     *         level: int,
     *         min_xp: int,
     *         description: string,
     *         achieved: bool
     *     }>,
     *     badges: list<array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         icon: string,
     *         unlocked: bool,
     *         progress: int,
     *         target: int
     *     }>
     * }
     */
    public function get(User $user, User $viewer): array
    {
        $visibleCommitsQuery = $user->commits()->visibleTo($viewer);

        $commitsCount = (int) (clone $visibleCommitsQuery)->count();
        $repliesCount = (int) (clone $visibleCommitsQuery)->whereNotNull('parent_id')->count();

        $visibleCommitIds = (clone $visibleCommitsQuery)->select('id');
        $starsReceivedCount = (int) DB::table('stars')->whereIn('commit_id', $visibleCommitIds)->count();

        $starsGivenCount = (int) $user->starredCommits()->count();

        $longestStreak = $this->streaks->get($user, $viewer)['longest'];

        $has280CharCommit = (clone $visibleCommitsQuery)->whereRaw('length(body) >= 280')->exists();
        $hasCliKey = $user->sshKeys()->exists();
        $hasSyntaxCommit = (clone $visibleCommitsQuery)->where('body', 'like', '%```%')->exists();

        // Calculate XP: 10 per commit, 5 per star received, 2 per star given, 5 per reply, 15 per day of longest streak
        $commitsXp = $commitsCount * 10;
        $streakXp = $longestStreak * 15;
        $starsReceivedXp = $starsReceivedCount * 5;
        $repliesXp = $repliesCount * 5;
        $starsGivenXp = $starsGivenCount * 2;

        $xp = $commitsXp + $streakXp + $starsReceivedXp + $repliesXp + $starsGivenXp;

        [$level, $rank, $nextLevelXp, $levelProgress] = $this->resolveRank($xp);

        $xpBreakdown = [
            'commits' => ['count' => $commitsCount, 'multiplier' => 10, 'xp' => $commitsXp],
            'streak' => ['count' => $longestStreak, 'multiplier' => 15, 'xp' => $streakXp],
            'stars_received' => ['count' => $starsReceivedCount, 'multiplier' => 5, 'xp' => $starsReceivedXp],
            'replies' => ['count' => $repliesCount, 'multiplier' => 5, 'xp' => $repliesXp],
            'stars_given' => ['count' => $starsGivenCount, 'multiplier' => 2, 'xp' => $starsGivenXp],
        ];

        $ranks = [
            ['rank' => 'user', 'level' => 1, 'min_xp' => 0, 'description' => 'Unprivileged user (UID 1000)', 'achieved' => true],
            ['rank' => 'daemon', 'level' => 2, 'min_xp' => 50, 'description' => 'Background process running in user space', 'achieved' => $xp >= 50],
            ['rank' => 'operator', 'level' => 3, 'min_xp' => 150, 'description' => 'System operator with elevated permissions', 'achieved' => $xp >= 150],
            ['rank' => 'maintainer', 'level' => 4, 'min_xp' => 400, 'description' => 'Codebase curator managing repository state', 'achieved' => $xp >= 400],
            ['rank' => 'wheel', 'level' => 5, 'min_xp' => 1000, 'description' => 'Member of the trusted wheel administrative group', 'achieved' => $xp >= 1000],
            ['rank' => 'root', 'level' => 6, 'min_xp' => 2500, 'description' => 'Superuser with full kernel privileges (UID 0)', 'achieved' => $xp >= 2500],
        ];

        $metrics = [
            Achievement::Init->value => [
                'progress' => min($commitsCount, Achievement::Init->target()),
                'target' => Achievement::Init->target(),
            ],
            Achievement::Uptime->value => [
                'progress' => min($longestStreak, Achievement::Uptime->target()),
                'target' => Achievement::Uptime->target(),
            ],
            Achievement::Daemon->value => [
                'progress' => min($longestStreak, Achievement::Daemon->target()),
                'target' => Achievement::Daemon->target(),
            ],
            Achievement::Stdout->value => [
                'progress' => min($commitsCount, Achievement::Stdout->target()),
                'target' => Achievement::Stdout->target(),
            ],
            Achievement::KernelSpace->value => [
                'progress' => min($commitsCount, Achievement::KernelSpace->target()),
                'target' => Achievement::KernelSpace->target(),
            ],
            Achievement::Stargazer->value => [
                'progress' => min($starsGivenCount, Achievement::Stargazer->target()),
                'target' => Achievement::Stargazer->target(),
            ],
            Achievement::Superstar->value => [
                'progress' => min($starsReceivedCount, Achievement::Superstar->target()),
                'target' => Achievement::Superstar->target(),
            ],
            Achievement::Pipe->value => [
                'progress' => min($repliesCount, Achievement::Pipe->target()),
                'target' => Achievement::Pipe->target(),
            ],
            Achievement::MaxPayload->value => [
                'progress' => $has280CharCommit ? 1 : 0,
                'target' => Achievement::MaxPayload->target(),
            ],
            Achievement::CliNative->value => [
                'progress' => $hasCliKey ? 1 : 0,
                'target' => Achievement::CliNative->target(),
            ],
            Achievement::Syntax->value => [
                'progress' => $hasSyntaxCommit ? 1 : 0,
                'target' => Achievement::Syntax->target(),
            ],
            Achievement::Sysadmin->value => [
                'progress' => min($longestStreak, Achievement::Sysadmin->target()),
                'target' => Achievement::Sysadmin->target(),
            ],
        ];

        $badges = array_map(function (Achievement $achievement) use ($metrics): array {
            $data = $metrics[$achievement->value];
            $progress = (int) $data['progress'];
            $target = (int) $data['target'];

            return [
                'id' => $achievement->value,
                'name' => $achievement->label(),
                'description' => $achievement->description(),
                'icon' => $achievement->icon(),
                'unlocked' => $progress >= $target,
                'progress' => $progress,
                'target' => $target,
            ];
        }, Achievement::cases());

        return [
            'level' => $level,
            'rank' => $rank,
            'xp' => $xp,
            'next_level_xp' => $nextLevelXp,
            'level_progress' => $levelProgress,
            'xp_breakdown' => $xpBreakdown,
            'ranks' => $ranks,
            'badges' => $badges,
        ];
    }

    /**
     * @return array{0: int, 1: string, 2: ?int, 3: int}
     */
    private function resolveRank(int $xp): array
    {
        return match (true) {
            $xp >= 2500 => [6, 'root', null, 100],
            $xp >= 1000 => [5, 'wheel', 2500, min(100, max(0, (int) round((($xp - 1000) / 1500) * 100)))],
            $xp >= 400 => [4, 'maintainer', 1000, min(100, max(0, (int) round((($xp - 400) / 600) * 100)))],
            $xp >= 150 => [3, 'operator', 400, min(100, max(0, (int) round((($xp - 150) / 250) * 100)))],
            $xp >= 50 => [2, 'daemon', 150, min(100, max(0, (int) round((($xp - 50) / 100) * 100)))],
            default => [1, 'user', 50, min(100, max(0, (int) round(($xp / 50) * 100)))],
        };
    }
}
