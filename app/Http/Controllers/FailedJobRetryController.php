<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateFailedJobRetry;
use App\Http\Requests\CreateFailedJobRetryRequest;
use Illuminate\Http\RedirectResponse;

final readonly class FailedJobRetryController
{
    public function store(CreateFailedJobRetryRequest $request, string $failedJob, CreateFailedJobRetry $action): RedirectResponse
    {
        $action->handle($failedJob);

        return back();
    }
}
