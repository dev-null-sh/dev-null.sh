<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateGuild
{
    public function __construct(private CreateImageFiles $images, private DeleteStoredFiles $deleteFiles, private DeleteGuildSubmission $deleteSubmission, private CreateGuildSubmission $submit)
    {
        //
    }

    /**
     * The icon is an upload, or the path of one already stored, such as a reviewed submission's.
     */
    public function handle(Guild $guild, User $user, string $name, string $description, string $emoji, UploadedFile|string|null $icon, bool $removeIcon, bool $flagged = false): void
    {
        DB::transaction(function () use ($guild, $user, $name, $description, $emoji, $icon, $removeIcon, $flagged): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $guild = Guild::query()->whereKey($guild->id)->lockForUpdate()->firstOrFail();

            if ($guild->user_id !== $user->id) {
                throw new GuildModeratorRemoved;
            }

            if ($flagged) {
                assert($icon instanceof UploadedFile || $icon === null);
                $this->submit->handle($user, $guild->handle, $name, $description, $emoji, $icon, $removeIcon);

                return;
            }

            $path = $guild->icon_path;

            if ($icon !== null || $removeIcon) {
                $this->deleteFiles->retain($path === null ? [] : [$path]);
                $path = $icon instanceof UploadedFile ? $this->images->handle([$icon], directory: 'guild-icons')[0]['path'] : $icon;
            }

            $guild->update(['name' => $name, 'description' => $description, 'emoji' => $emoji, 'icon_path' => $path]);
            $submission = GuildSubmission::query()->where('user_id', $user->id)->where('handle', $guild->handle)->first();

            if ($submission instanceof GuildSubmission) {
                $this->deleteSubmission->handle($submission);
            }

            event(new UsersUpdated(DB::table('guild_preferences')->where('guild_id', $guild->id)->pluck('user_id')->push($guild->user_id), UserUpdate::Guilds));
        });
    }
}
