<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildCommitUnavailable;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuildCommitPin
{
    public function __construct(private GuildCommitsQuery $commits)
    {
        //
    }

    public function handle(Guild $guild, User $user, Commit $commit, bool $pinned): void
    {
        DB::transaction(function () use ($guild, $user, $commit, $pinned): void {
            if ($pinned && Commit::query()->whereKey($commit->id)->sharedLock()->first() === null) {
                throw new GuildCommitUnavailable;
            }

            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($guild->user_id !== $user->id) {
                throw new GuildModeratorRemoved;
            }

            if ($pinned && $this->commits->rootIds($guild, $user, $commit->id)->isEmpty()) {
                throw new GuildCommitUnavailable;
            }

            Guild::withoutTimestamps(fn (): int => Guild::query()->whereKey($guild->id)
                ->unless($pinned, fn (Builder $query): Builder => $query->where('pinned_commit_id', $commit->id))
                ->update(['pinned_commit_id' => $pinned ? $commit->id : null]));

            event(new UsersUpdated(DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($user->id), UserUpdate::Guilds));
        });
    }
}
