<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserReport;
use App\Actions\UpdateUserReportDismissedAt;
use App\Http\Requests\CreateUserReportRequest;
use App\Http\Requests\DeleteUserReportRequest;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserReportController
{
    public function store(CreateUserReportRequest $request, #[CurrentUser] User $reporter, User $user, CreateUserReport $action): RedirectResponse
    {
        $action->handle($reporter, $user, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Report received. Moderators will review it privately.']);

        return back();
    }

    public function destroy(DeleteUserReportRequest $request, UserReport $report, UpdateUserReportDismissedAt $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
