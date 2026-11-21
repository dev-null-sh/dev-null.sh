<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\GuildHandleTaken;
use App\Exceptions\GuildModeratorRemoved;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateGuildSubmissionApproval
{
    public function __construct(private CreateGuild $createGuild, private UpdateGuild $updateGuild, private DeleteGuildSubmission $deleteSubmission)
    {
        //
    }

    /**
     * Publishes the submission as if it had passed the moderation: an edit when its author still moderates the guild,
     * otherwise a new guild, which claims a paused one and leaves one someone else moderates alone. An author removed
     * from the guild meanwhile gets nothing, and neither does a hashtag that became another guild's alias.
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

            $guild = Guild::query()->where('handle', $submission->handle)->first();

            try {
                if ($guild instanceof Guild && $guild->user_id === $submission->user_id) {
                    $this->updateGuild->handle($guild, $submission->user, $submission->name, $submission->description, $submission->emoji, $submission->icon_path, $submission->remove_icon);
                } else {
                    $this->createGuild->handle($submission->user, $submission->handle, $submission->name, $submission->description, $submission->emoji, $submission->icon_path);
                }
            } catch (GuildHandleTaken|GuildModeratorRemoved) {
                //
            }

            $this->deleteSubmission->handle($submission);
        });
    }
}
