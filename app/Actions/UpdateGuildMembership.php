<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuildMembership
{
    public function handle(User $user, Guild $guild, bool $joined): void
    {
        DB::transaction(function () use ($user, $guild, $joined): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Guild::query()->whereKey($guild->id)->sharedLock()->firstOrFail();
            // A repeated join keeps when the user joined, so the members list keeps its order...
            $joinedAt = DB::table('guild_preferences')->where('user_id', $user->id)->where('guild_id', $guild->id)->value('joined_at');
            DB::table('guild_preferences')->updateOrInsert(
                ['user_id' => $user->id, 'guild_id' => $guild->id],
                ['joined_at' => $joined ? ($joinedAt ?? now()) : null, ...($joined ? ['muted_at' => null] : [])],
            );
            DB::table('guild_preferences')->where('user_id', $user->id)->where('guild_id', $guild->id)
                ->whereNull('joined_at')->whereNull('muted_at')->delete();
            event(new UsersUpdated(DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($user->id), UserUpdate::Guilds));
            event(new UsersUpdated([$user->id], UserUpdate::Relationships));
            event(new ProfilesUpdated([$user->id]));
        });
    }
}
