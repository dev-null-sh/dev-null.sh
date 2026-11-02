<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteFailedJobs;
use App\Http\Requests\DeleteFailedJobRequest;
use Illuminate\Http\RedirectResponse;

final readonly class FailedJobsController
{
    public function destroy(DeleteFailedJobRequest $request, DeleteFailedJobs $action): RedirectResponse
    {
        $action->handle();

        return back();
    }
}
