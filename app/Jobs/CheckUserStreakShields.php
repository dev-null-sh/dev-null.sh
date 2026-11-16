<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateStreakShieldNotifications;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * One user's replay is one job, so a slow history cannot stop the hourly scan notifying everyone after them.
 */
#[DeleteWhenMissingModels]
final class CheckUserStreakShields implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user) {}

    public function uniqueId(): string
    {
        return $this->user->id;
    }

    public function handle(CreateStreakShieldNotifications $action): void
    {
        if ($this->user->banned_at === null) {
            $action->handle($this->user);
        }
    }
}
