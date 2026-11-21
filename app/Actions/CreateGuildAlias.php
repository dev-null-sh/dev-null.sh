<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\GuildAlias;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuildAlias
{
    /**
     * Held while a hashtag becomes a guild's handle or alias, so it never becomes both.
     */
    public const string HANDLE_LOCK = 'guild-handle:';

    /**
     * The alias is checked again under the lock, and left out when its hashtag was taken meanwhile or the user no
     * longer moderates the guild.
     */
    public function handle(Guild $guild, User $user, string $handle): void
    {
        DB::transaction(function () use ($guild, $user, $handle): void {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [self::HANDLE_LOCK.$handle]);
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($guild->user_id !== $user->id || Guild::query()->where('handle', $handle)->exists() || GuildAlias::query()->where('handle', $handle)->exists()) {
                return;
            }

            $guild->aliases()->create(['handle' => $handle]);

            event(new UsersUpdated(DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($guild->user_id), UserUpdate::Guilds));
        });
    }
}
