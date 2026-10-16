<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteFailedJob;
use App\Http\Requests\DeleteFailedJobRequest;
use Illuminate\Http\RedirectResponse;

final readonly class FailedJobController
{
    public function destroy(DeleteFailedJobRequest $request, string $failedJob, DeleteFailedJob $action): RedirectResponse
    {
        $action->handle($failedJob);

        return back();
    }
}
