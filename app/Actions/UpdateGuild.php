<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorNotMember;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuild
{
    public function __construct(private CreateImageFiles $images, private DeleteStoredFiles $deleteFiles)
    {
        //
    }

    /**
     * A new moderator must still be a member when the guild is saved. Their row is locked before the guild, like joining does,
     * so they cannot leave halfway through the handover.
     */
    public function handle(Guild $guild, User $user, string $description, string $emoji, ?UploadedFile $icon, bool $removeIcon, ?User $moderator = null): void
    {
        DB::transaction(function () use ($guild, $user, $description, $emoji, $icon, $removeIcon, $moderator): void {
            $handover = $moderator instanceof User && $moderator->id !== $user->id;

            if ($handover) {
                User::query()->whereKey($moderator->id)->lockForUpdate()->firstOrFail();
            }

            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($guild->user_id !== $user->id) {
                throw new GuildModeratorRemoved;
            }

            if ($handover && ! $guild->members()->whereKey($moderator->id)->exists()) {
                throw new GuildModeratorNotMember;
            }

            $path = $guild->icon_path;

            if ($icon instanceof UploadedFile || $removeIcon) {
                $this->deleteFiles->retain($path === null ? [] : [$path]);
                $path = $icon instanceof UploadedFile ? $this->images->handle([$icon], directory: 'guild-icons')[0]['path'] : null;
            }

            $guild->update([
                'description' => $description,
                'emoji' => $emoji,
                'icon_path' => $path,
                'user_id' => $handover ? $moderator->id : $guild->user_id,
            ]);

            $viewers = DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($user->id);
            event(new UsersUpdated($viewers, UserUpdate::Guilds));

            if ($handover) {
                event(new UsersUpdated($viewers, UserUpdate::Relationships));
            }
        });
    }
}
