<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateLevelNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Runs after anything that can earn XP. While one check waits in the queue, more for the same user are dropped: a
 * burst of stars needs a single look at the level once they are all in.
 */
#[DeleteWhenMissingModels]
final class CheckUserLevel implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->user->id;
    }

    public function handle(CreateLevelNotification $action): void
    {
        $action->handle($this->user);
    }
}
