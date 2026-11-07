<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateUserReport;
use App\Http\Requests\CreateApiUserReportRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class UserReportController
{
    /**
     * Reports are private, so there is nothing to answer with. Reporting again keeps the first report.
     */
    public function store(CreateApiUserReportRequest $request, #[CurrentUser] User $currentUser, User $user, CreateUserReport $action): Response
    {
        $action->handle($currentUser, $user, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null);

        return response()->noContent();
    }
}
