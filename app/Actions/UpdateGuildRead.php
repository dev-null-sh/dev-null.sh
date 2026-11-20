<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuildRead
{
    public function handle(User $user, Guild $guild): void
    {
        $updated = DB::table('guild_preferences')->where('user_id', $user->id)->where('guild_id', $guild->id)
            ->whereNotNull('joined_at')->update(['read_at' => now()]);

        if ($updated > 0) {
            event(new UsersUpdated([$user->id], UserUpdate::Guilds));
        }
    }
}
