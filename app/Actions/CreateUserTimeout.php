<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ModerationReason;
use App\Enums\TimeoutDuration;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserTimeout
{
    public function __construct(private CreateUserTimeoutNotification $createNotification)
    {
        //
    }

    /**
     * Every timeout is kept once it's over, so moderators can see how many the user has had.
     */
    public function handle(User $user, ModerationReason $reason, TimeoutDuration $duration): UserTimeout
    {
        $timeout = DB::transaction(function () use ($user, $reason, $duration): UserTimeout {
            $timeout = $user->timeouts()->create([
                'reason' => $reason,
                'ends_at' => now()->addMinutes($duration->value),
            ]);

            $this->createNotification->handle($timeout);

            return $timeout;
        });

        event(new UsersUpdated([$user->id], UserUpdate::Account));

        return $timeout;
    }
}
