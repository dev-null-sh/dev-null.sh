<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ClearStaleTagVerifications as ClearStaleTagVerificationsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ClearStaleTagVerifications implements ShouldQueue
{
    use Queueable;

    public function handle(ClearStaleTagVerificationsAction $action): void
    {
        $action->handle();
    }
}
