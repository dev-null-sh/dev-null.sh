<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RefreshBranchSiteMeta as RefreshBranchSiteMetaAction;
use App\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshBranchSiteMeta implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $branchId = null)
    {
        //
    }

    public function handle(RefreshBranchSiteMetaAction $action): void
    {
        if ($this->branchId === null) {
            $action->handleVerified();

            return;
        }

        $branch = Branch::query()->find($this->branchId);

        if ($branch instanceof Branch) {
            $action->handle($branch);
        }
    }
}
