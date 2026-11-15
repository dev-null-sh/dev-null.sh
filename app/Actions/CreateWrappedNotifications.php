<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class CreateWrappedNotifications
{
    /**
     * Tells every handle with an approved commit that year that their Wrapped is ready, linking them to their own
     * review. One row per handle per year, so running it again changes nothing. Never to bots. Safe to call more
     * than once.
     */
    public function handle(int $year): int
    {
        $recipientIds = User::query()
            ->whereNotIn('github_id', User::BOT_GITHUB_IDS)
            ->whereHas(
                'commits',
                fn (Builder $commits): Builder => $commits
                    ->where('status', CommitStatus::Approved)
                    ->whereYear('created_at', $year),
            )
            ->pluck('users.id');

        $now = now();

        $notifications = [];

        foreach ($recipientIds as $recipientId) {
            $notifications[] = [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $recipientId,
                'actor_id' => $recipientId,
                'type' => NotificationType::WrappedReady->value,
                'commit_id' => null,
                'key' => 'wrapped:'.$year,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($notifications, 500) as $chunk) {
            Notification::query()->insertOrIgnore($chunk);
        }

        if ($recipientIds->isNotEmpty()) {
            event(new UsersUpdated($recipientIds->all(), UserUpdate::Notifications));
        }

        return count($notifications);
    }
}
