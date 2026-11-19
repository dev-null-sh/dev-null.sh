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
    public function __construct(private DeleteGuildLiveNotifications $notifications)
    {
        //
    }

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

            // The scheduler selected this guild before taking the lock. A moderator may have started a new stream
            // since then, and that stream is still running.
            if (! $moderator instanceof User && $guild->live_ends_at?->isFuture() === true) {
                return;
            }

            if ($guild->live_platform === null && $guild->live_url === null && $guild->live_ends_at === null) {
                return;
            }

            $guild->update(['live_platform' => null, 'live_url' => null, 'live_ends_at' => null]);

            $this->notifications->handle($guild);

            event(new UsersUpdated($guild->viewerIds(), UserUpdate::Guilds));
        });
    }
}
