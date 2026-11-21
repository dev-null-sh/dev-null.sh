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

final readonly class DeleteCommitEscalation
{
    public function handle(Commit $commit): void
    {
        if ($commit->escalated_at === null) {
            return;
        }

        /** @var Collection<int, User> $admins */
        $admins = User::query()->get()->filter(fn (User $user): bool => $user->isAdmin());

        DB::transaction(function () use ($commit): void {
            $commit->forceFill(['escalated_at' => null])->save();

            Notification::query()
                ->where('type', NotificationType::Escalated)
                ->where('key', 'escalated:'.$commit->id)
                ->delete();
        });

        if ($admins->isNotEmpty()) {
            event(new UsersUpdated($admins->pluck('id')->all(), UserUpdate::Notifications));
        }
    }
}
