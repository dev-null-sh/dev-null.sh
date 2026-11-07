<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserReportDismissedAt;
use App\Http\Requests\DeleteUserReportRequest;
use App\Models\UserReport;
use Illuminate\Http\RedirectResponse;

final readonly class UserReportController
{
    public function destroy(DeleteUserReportRequest $request, UserReport $report, UpdateUserReportDismissedAt $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
