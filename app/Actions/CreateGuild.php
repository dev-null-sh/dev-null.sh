<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuild
{
    public function __construct(private CreateImageFiles $images, private UpdateGuildMembership $membership, private DeleteGuildSubmission $deleteSubmission, private CreateGuildSubmission $submit)
    {
        //
    }

    /**
     * The icon is an upload, or the path of one already stored, such as a reviewed submission's.
     */
    public function handle(User $user, string $handle, string $name, string $description, string $emoji, UploadedFile|string|null $icon, bool $flagged = false): Guild|GuildSubmission
    {
        return DB::transaction(function () use ($user, $handle, $name, $description, $emoji, $icon, $flagged): Guild|GuildSubmission {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (DB::table('guild_moderator_removals')->where('github_id', $user->github_id)->where('handle', mb_strtolower($handle))->exists()) {
                throw new GuildModeratorRemoved;
            }

            if ($flagged) {
                // A guild another creation published after validation keeps its hashtag, so nothing waits for review...
                $taken = Guild::query()->where('handle', mb_strtolower($handle))->whereNotNull('user_id')->first();

                if ($taken instanceof Guild) {
                    return $taken;
                }

                assert($icon instanceof UploadedFile || $icon === null);

                return $this->submit->handle($user, mb_strtolower($handle), $name, $description, $emoji, $icon, false);
            }

            $guild = Guild::query()->createOrFirst(['handle' => mb_strtolower($handle)], [
                'user_id' => $user->id,
                'name' => $name,
                'description' => $description,
                'emoji' => $emoji,
            ]);

            if ($guild->wasRecentlyCreated) {
                if ($icon !== null) {
                    $guild->update(['icon_path' => $icon instanceof UploadedFile ? $this->images->handle([$icon], directory: 'guild-icons')[0]['path'] : $icon]);
                }

                $this->membership->handle($user, $guild, true);
            } else {
                $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

                if ($guild->user_id === null) {
                    $guild->update(['user_id' => $user->id]);
                    $this->membership->handle($user, $guild, true);
                }
            }

            $submission = GuildSubmission::query()->where('user_id', $user->id)->where('handle', $guild->handle)->first();

            if ($submission instanceof GuildSubmission) {
                $this->deleteSubmission->handle($submission);
            }

            return $guild;
        });
    }
}
