<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReportedUsersQuery
{
    public function __construct(private UserModerationQuery $moderation)
    {
        //
    }

    /**
     * The open reports on people who are not banned, child safety first and then oldest first, each with what moderators can do about that person.
     * Admins have no moderation menu, as on their profile. The reporter stays out of it.
     *
     * @return CursorPaginator<int, array<mixed>>
     */
    public function paginate(): CursorPaginator
    {
        $reports = UserReport::query()
            ->select(['id', 'user_id', 'reason', 'details', 'created_at'])
            ->selectRaw("(reason = 'child_safety')::int as priority")
            ->whereNull('dismissed_at')
            ->whereHas('user', fn (Builder $query): Builder => $query->whereNull('banned_at'))
            ->with('user')
            ->orderByDesc('priority')->oldest()->oldest('id')
            ->cursorPaginate(20);

        $users = collect($reports->items())->map(fn (UserReport $report): User => $report->user)->reject(fn (User $user): bool => $user->isAdmin())->unique('id');
        $moderation = $this->moderation->get($users);

        return $reports->through(fn (UserReport $report): array => [...$report->toArray(), 'moderation' => $moderation[$report->user_id] ?? null]);
    }
}
