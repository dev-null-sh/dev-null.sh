<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\GuildAlias;
use Illuminate\Support\Facades\DB;

final readonly class DeleteGuildAlias
{
    public function handle(Guild $guild, GuildAlias $alias): void
    {
        $alias->delete();

        event(new UsersUpdated(DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($guild->user_id), UserUpdate::Guilds));
    }
}
