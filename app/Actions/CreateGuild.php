<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuild
{
    public function __construct(private CreateImageFiles $images, private UpdateGuildMembership $membership)
    {
        //
    }

    public function handle(User $user, string $handle, string $name, string $description, string $emoji, ?UploadedFile $icon): Guild
    {
        return DB::transaction(function () use ($user, $handle, $name, $description, $emoji, $icon): Guild {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (DB::table('guild_moderator_removals')->where('github_id', $user->github_id)->where('handle', mb_strtolower($handle))->exists()) {
                throw new GuildModeratorRemoved;
            }

            $guild = Guild::query()->createOrFirst(['handle' => mb_strtolower($handle)], [
                'user_id' => $user->id,
                'name' => $name,
                'description' => $description,
                'emoji' => $emoji,
            ]);

            if ($guild->wasRecentlyCreated) {
                if ($icon instanceof UploadedFile) {
                    $guild->update(['icon_path' => $this->images->handle([$icon], directory: 'guild-icons')[0]['path']]);
                }

                $this->membership->handle($user, $guild, true);
            }

            return $guild;
        });
    }
}
