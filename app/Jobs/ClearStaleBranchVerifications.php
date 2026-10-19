<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ClearStaleBranchVerifications as ClearStaleBranchVerificationsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ClearStaleBranchVerifications implements ShouldQueue
{
    use Queueable;

    public function handle(ClearStaleBranchVerificationsAction $action): void
    {
        $action->handle();
    }
}
