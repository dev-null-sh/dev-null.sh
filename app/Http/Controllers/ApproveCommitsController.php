<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveCommits;
use App\Http\Requests\ApproveCommitsRequest;
use Illuminate\Http\RedirectResponse;

final readonly class ApproveCommitsController
{
    public function __invoke(ApproveCommitsRequest $request, ApproveCommits $action): RedirectResponse
    {
        /** @var array<int, string> $commitIds */
        $commitIds = $request->validated('commits');

        $action->handle($commitIds);

        return back();
    }
}
