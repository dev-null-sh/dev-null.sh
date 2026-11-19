<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LivePlatform;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuildLive
{
    /**
     * Points the guild at a YouTube or Twitch stream for a few hours. Only its current moderator can do this.
     */
    public function handle(Guild $guild, User $user, LivePlatform $platform, string $url, int $hours): void
    {
        DB::transaction(function () use ($guild, $user, $platform, $url, $hours): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($guild->user_id !== $user->id) {
                throw new GuildModeratorRemoved;
            }

            $guild->update([
                'live_platform' => $platform,
                'live_url' => $platform->driver()->stream($url)->url,
                'live_ends_at' => now()->addHours($hours),
            ]);

            event(new UsersUpdated(
                DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($guild->user_id)->filter()->unique()->values(),
                UserUpdate::Guilds,
            ));
        });
    }
};
