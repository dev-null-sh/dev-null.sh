<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class DeleteGuildSubmission
{
    public function __construct(private DeleteStoredFiles $deleteFiles, private AdminsQuery $admins)
    {
        //
    }

    /**
     * Its icon goes with it, unless an approval just gave it to the guild.
     */
    public function handle(?GuildSubmission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            if (! $submission instanceof GuildSubmission || ! User::query()->whereKey($submission->user_id)->lockForUpdate()->first() instanceof User) {
                return;
            }

            $submission = GuildSubmission::query()->whereKey($submission->id)->lockForUpdate()->first();

            if (! $submission instanceof GuildSubmission) {
                return;
            }

            $iconPath = $submission->icon_path;
            $this->deleteFiles->retain($iconPath === null || Guild::query()->where('icon_path', $iconPath)->exists() ? [] : [$iconPath]);
            $submission->delete();
            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));
        });
    }
}
