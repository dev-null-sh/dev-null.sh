<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class DeleteGuildModerator
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(Guild $guild): void
    {
        DB::transaction(function () use ($guild): void {
            $moderator = User::query()->whereKey($guild->user_id)->lockForUpdate()->first();
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($moderator instanceof User && $guild->user_id === $moderator->id) {
                DB::table('guild_moderator_removals')->insertOrIgnore([
                    'github_id' => $moderator->github_id, 'handle' => $guild->handle, 'created_at' => now(),
                ]);
                $guild->update(['user_id' => null]);
            }

            $viewers = DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id');
            event(new UsersUpdated($viewers, UserUpdate::Guilds));
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));
            event(new UsersUpdated($viewers, UserUpdate::Relationships));
        });
    }
}
