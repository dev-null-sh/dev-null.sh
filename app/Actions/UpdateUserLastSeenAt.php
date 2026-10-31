<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserLastSeenAt
{
    /**
     * Remembers the day someone was last seen, not the time, so it is written at most once a day. The first time they
     * are seen in a day also records that day for them, for retention, and counts them in that day's active users.
     * Only the request whose update moves the day does, so two requests at once can't count someone twice. It leaves
     * updated_at alone, which the sitemap reads as the day the profile last changed.
     */
    public function handle(User $user): void
    {
        if ($user->last_seen_at?->isToday() === true) {
            return;
        }

        $today = today()->toDateString();

        $moved = User::query()
            ->whereKey($user->id)
            ->where(fn (Builder $query): Builder => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $today))
            ->toBase()
            ->update(['last_seen_at' => $today]);

        $user->forceFill(['last_seen_at' => $today])->syncOriginalAttribute('last_seen_at');

        if ($moved === 0) {
            return;
        }

        DB::table('user_active_days')->insertOrIgnore(['user_id' => $user->id, 'seen_at' => $today]);
        DB::table('active_user_counts')->upsert([['seen_at' => $today, 'count' => 1]], ['seen_at'], ['count' => DB::raw('active_user_counts.count + 1')]);
    }
}
