<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuildMembership
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(User $user, Guild $guild, bool $joined): void
    {
        DB::transaction(function () use ($user, $guild, $joined): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Guild::query()->whereKey($guild->id)->sharedLock()->firstOrFail();
            DB::table('guild_preferences')->updateOrInsert(
                ['user_id' => $user->id, 'guild_id' => $guild->id],
                ['joined_at' => $joined ? now() : null],
            );
            DB::table('guild_preferences')->where('user_id', $user->id)->where('guild_id', $guild->id)
                ->whereNull('joined_at')->whereNull('muted_at')->delete();
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Guilds));
            event(new UsersUpdated([$user->id], UserUpdate::Relationships));
        });
    }
}
