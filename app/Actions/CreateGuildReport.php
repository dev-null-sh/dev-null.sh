<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\GuildReport;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuildReport
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(User $user, Guild $guild, string $details): void
    {
        DB::transaction(function () use ($user, $guild, $details): void {
            User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            Guild::query()->whereKey($guild->id)->sharedLock()->firstOrFail();
            $report = GuildReport::query()->createOrFirst(['guild_id' => $guild->id, 'reporter_id' => $user->id], ['details' => $details]);

            if ($report->dismissed_at !== null) {
                $report->update(['details' => $details, 'dismissed_at' => null]);
            }

            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));
        });
    }
}
