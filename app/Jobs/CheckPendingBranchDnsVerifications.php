<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CheckPendingBranchDnsVerifications as CheckPendingBranchDnsVerificationsAction;
use App\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CheckPendingBranchDnsVerifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?Branch $branch = null)
    {
        //
    }

    public function handle(CheckPendingBranchDnsVerificationsAction $action): void
    {
        $action->handle($this->branch);
    }
}
