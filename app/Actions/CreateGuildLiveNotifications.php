<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateGuildLiveNotifications
{
    public function __construct(private DeleteGuildLiveNotifications $delete)
    {
        //
    }

    /**
     * Tells everyone who has joined the guild, and not muted it, that it is live. The moderator who started it
     * already knows. A previous alert for this guild is replaced, so going live again is unread.
     */
    public function handle(Guild $guild, User $actor): void
    {
        $this->delete->handle($guild);

        $recipientIds = DB::table('guild_preferences')
            ->where('guild_id', $guild->id)
            ->whereNotNull('joined_at')
            ->whereNull('muted_at')
            ->where('user_id', '!=', $actor->id)
            ->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($recipientIds as $recipientId) {
            if (! is_string($recipientId)) {
                continue;
            }

            $rows[] = [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $recipientId,
                'actor_id' => $actor->id,
                'type' => NotificationType::GuildLive->value,
                'guild_id' => $guild->id,
                'key' => 'guild_live:'.$guild->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::query()->insert($chunk);
        }

        event(new UsersUpdated($recipientIds, UserUpdate::Notifications));
    }
}
