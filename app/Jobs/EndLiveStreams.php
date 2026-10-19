<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteUserLive;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class EndLiveStreams implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Clears the streams that ran out. They already stopped showing as live when they did.
     */
    public function handle(DeleteUserLive $action): void
    {
        User::query()->where('live_ends_at', '<=', now())->each($action->handle(...));
    }
}
