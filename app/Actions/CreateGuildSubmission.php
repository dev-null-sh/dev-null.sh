<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Queries\AdminsQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuildSubmission
{
    public function __construct(private CreateImageFiles $images, private DeleteStoredFiles $deleteFiles, private AdminsQuery $admins)
    {
        //
    }

    /**
     * Holds a guild, or an edit of one, for the admins to review. A later submission for the same hashtag replaces the
     * one still waiting.
     */
    public function handle(User $user, string $handle, string $name, string $description, string $emoji, ?UploadedFile $icon, bool $removeIcon): GuildSubmission
    {
        return DB::transaction(function () use ($user, $handle, $name, $description, $emoji, $icon, $removeIcon): GuildSubmission {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = GuildSubmission::query()->where('user_id', $user->id)->where('handle', $handle)->lockForUpdate()->first();
            $this->deleteFiles->retain($previous?->icon_path === null ? [] : [$previous->icon_path]);
            $previous?->delete();

            $submission = GuildSubmission::query()->create([
                'user_id' => $user->id,
                'handle' => $handle,
                'name' => $name,
                'description' => $description,
                'emoji' => $emoji,
                'icon_path' => $icon instanceof UploadedFile ? $this->images->handle([$icon], directory: 'guild-icons')[0]['path'] : null,
                'remove_icon' => $removeIcon,
            ]);

            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));

            return $submission;
        });
    }
}
