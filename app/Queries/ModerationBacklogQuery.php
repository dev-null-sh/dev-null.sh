<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\UserReport;
use App\Models\UserTimeout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type ModerationBacklog array{pending: int, oldest_pending_age: int|null, open_reports: int, open_profile_reports: int, active_timeouts: int}
 */
final readonly class ModerationBacklogQuery
{
    /**
     * What waits on a moderator: the pending commits and how long the oldest has waited, in seconds, the reports the
     * reports pages still list, of which some are about people, and the people in a timeout that has not ended.
     *
     * @return ModerationBacklog
     */
    public function get(): array
    {
        /** @var object{pending: int, oldest_created_at: string|null} $pending */
        $pending = Commit::query()->pending()->toBase()->selectRaw('count(*) as pending, min(created_at) as oldest_created_at')->first();

        $openProfileReports = UserReport::query()
            ->whereNull('dismissed_at')
            ->whereHas('user', fn (Builder $query): Builder => $query->whereNull('banned_at'))
            ->count();

        return [
            'pending' => (int) $pending->pending,
            'oldest_pending_age' => $pending->oldest_created_at === null
                ? null
                : max(0, now()->getTimestamp() - CarbonImmutable::parse($pending->oldest_created_at)->getTimestamp()),
            'open_reports' => CommitReport::query()
                ->whereNull('dismissed_at')
                ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->approved())
                ->count() + $openProfileReports,
            'open_profile_reports' => $openProfileReports,
            'active_timeouts' => UserTimeout::query()->where('ends_at', '>', now())->count(),
        ];
    }
}
