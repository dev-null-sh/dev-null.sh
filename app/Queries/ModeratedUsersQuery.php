<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Ban;
use App\Models\User;
use App\Models\UserTimeout;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class ModeratedUsersQuery
{
    /**
     * Everyone in timeout right now, once each with the timeout that ends last, the soonest out first. A timeout whose user
     * deleted their account is left out: it runs out on its own, and applies again only if the account signs up again.
     *
     * @return array<int, array{id: string, user: User, reason: string, ends_at: CarbonInterface, timeouts_count: int}>
     */
    public function timeouts(): array
    {
        return UserTimeout::query()
            ->where('ends_at', '>', now())
            ->with(['user' => fn (BelongsTo $query): BelongsTo => $query->withCount('timeouts')])
            ->latest('ends_at')
            ->get()
            ->unique('github_id')
            ->sortBy('ends_at')
            ->flatMap(fn (UserTimeout $timeout): array => $timeout->user instanceof User ? [[
                'id' => $timeout->id,
                'user' => $timeout->user,
                'reason' => $timeout->reason->label(),
                'ends_at' => $timeout->ends_at,
                'timeouts_count' => $timeout->user->timeouts_count,
            ]] : [])
            ->values()
            ->all();
    }

    /**
     * Every ban, the newest first, with its user unless they deleted their account, and the GitHub id either way, so bans on
     * deleted accounts can be told apart.
     *
     * @return array<int, array{id: string, github_id: string, user: User|null, reason: string, created_at: CarbonInterface}>
     */
    public function bans(): array
    {
        return Ban::query()
            ->with('user')
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Ban $ban): array => [
                'id' => $ban->id,
                'github_id' => $ban->github_id,
                'user' => $ban->user,
                'reason' => $ban->reason->label(),
                'created_at' => $ban->created_at,
            ])
            ->values()
            ->all();
    }
}
