<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class DeleteGuild
{
    public function __construct(private DeleteStoredFiles $files, private AdminsQuery $admins)
    {
        //
    }

    public function handle(Guild $guild): void
    {
        DB::transaction(function () use ($guild): void {
            $moderator = User::query()->whereKey($guild->user_id)->lockForUpdate()->firstOrFail();
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();
            DB::table('guild_moderator_removals')->insertOrIgnore([
                'github_id' => $moderator->github_id, 'handle' => $guild->handle, 'created_at' => now(),
            ]);
            $this->files->retain($guild->icon_path === null ? [] : [$guild->icon_path]);
            $viewers = DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id');
            $guild->delete();
            event(new UsersUpdated($viewers, UserUpdate::Guilds));
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));
            event(new UsersUpdated($viewers, UserUpdate::Relationships));
        });
    }
}
