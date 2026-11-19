<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteGuildLive
{
    /**
     * Clears the guild's stream. A moderator must still be the moderator. The scheduler passes no moderator, because
     * the deadline already passed.
     */
    public function handle(Guild $guild, ?User $moderator = null): void
    {
        DB::transaction(function () use ($guild, $moderator): void {
            if ($moderator instanceof User) {
                User::query()->whereKey($moderator->id)->lockForUpdate()->firstOrFail();
            }

            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($moderator instanceof User && $guild->user_id !== $moderator->id) {
                throw new GuildModeratorRemoved;
            }

            if ($guild->live_platform === null && $guild->live_url === null && $guild->live_ends_at === null) {
                return;
            }

            $guild->update(['live_platform' => null, 'live_url' => null, 'live_ends_at' => null]);

            event(new UsersUpdated(
                DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($guild->user_id)->filter()->unique()->values(),
                UserUpdate::Guilds,
            ));
        });
    }
};
