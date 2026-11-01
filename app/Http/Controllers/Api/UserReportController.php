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
     * Reports are private, so there is nothing to answer with. Reporting someone again keeps the report still open, or raises it for child safety.
     */
    public function store(CreateApiUserReportRequest $request, #[CurrentUser] User $reporter, User $user, CreateUserReport $action): Response
    {
        $action->handle($reporter, $user, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null);

        return response()->noContent();
    }
}
