<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use App\Models\UserReport;
use App\Queries\AdminsQuery;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserReport
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(User $user, User $reported, string $reason, ?string $details): void
    {
        DB::transaction(function () use ($user, $reported, $reason, $details): void {
            // Match account deletion's lock order and recheck accounts that may have been deleted since the request began.
            User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            User::query()->whereKey($reported->id)->sharedLock()->firstOrFail();

            $report = UserReport::query()->createOrFirst([
                'user_id' => $reported->id,
                'reporter_id' => $user->id,
            ], [
                'reason' => $reason,
                'details' => $details,
            ]);

            event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['user_reports' => [$report->id]]));
        });
    }
}
