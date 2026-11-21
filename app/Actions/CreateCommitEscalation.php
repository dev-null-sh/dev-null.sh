<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateCommitEscalation
{
    public function handle(User $moderator, Commit $commit): void
    {
        if ($commit->escalated_at !== null) {
            return;
        }

        /** @var Collection<int, User> $admins */
        $admins = User::query()->get()->filter(fn (User $user): bool => $user->isAdmin());

        if ($admins->isEmpty()) {
            $commit->forceFill(['escalated_at' => now()])->save();

            return;
        }

        $notifications = $admins->map(fn (User $admin): array => [
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $admin->id,
            'actor_id' => $moderator->id,
            'type' => NotificationType::Escalated->value,
            'commit_id' => $commit->id,
            'key' => 'escalated:'.$commit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::transaction(function () use ($commit, $notifications): void {
            $commit->forceFill(['escalated_at' => now()])->save();

            Notification::query()->upsert($notifications, ['recipient_id', 'key']);
        });

        event(new UsersUpdated($admins->pluck('id')->all(), UserUpdate::Notifications));
    }
}
