<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActiveDayApp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserLastSeenAt
{
    /**
     * Remembers the day someone was last seen, not the time, so it is written at most once a day. The first time they
     * are seen in a day also counts them in that day's active users. Only the request whose update moves the day does,
     * so two requests at once can't count someone twice. It leaves updated_at alone, which the sitemap reads as the day
     * the profile last changed.
     *
     * Each request also records the day and the app, for retention and to know which apps people use. A row is only
     * written the first time an app is seen in a day: `not exists` makes every other request a read, as an upsert that
     * only skips its update would still lock the row. Selecting from users means an account deleted by this very request
     * gets no row. The count comes first, so failing to record an app can't lose someone from the active users.
     */
    public function handle(User $user, ActiveDayApp $app): void
    {
        $today = today()->toDateString();
        $moved = 0;

        if ($user->last_seen_at?->isToday() !== true) {
            $moved = User::query()
                ->whereKey($user->id)
                ->where(fn (Builder $query): Builder => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $today))
                ->toBase()
                ->update(['last_seen_at' => $today]);

            $user->forceFill(['last_seen_at' => $today])->syncOriginalAttribute('last_seen_at');
        }

        if ($moved > 0) {
            DB::table('active_user_counts')->upsert([['seen_at' => $today, 'count' => 1]], ['seen_at'], ['count' => DB::raw('active_user_counts.count + 1')]);
        }

        DB::statement(<<<SQL
            insert into user_active_days (user_id, seen_at, {$app->value})
            select id, ?::date, true from users
            where id = ? and not exists (select 1 from user_active_days where user_id = ? and seen_at = ?::date and {$app->value})
            on conflict (user_id, seen_at) do update set {$app->value} = true
            SQL, [$today, $user->id, $user->id, $today]);
    }
}
