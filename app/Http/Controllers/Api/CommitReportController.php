<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitReport;
use App\Http\Requests\CreateApiCommitReportRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class CommitReportController
{
    /**
     * Reports are private, so there is nothing to answer with. Reporting a commit again keeps the first report.
     */
    public function store(CreateApiCommitReportRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitReport $action): Response
    {
        $action->handle($user, $commit, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null, $request->images(), $request->imageDescriptions());

        return response()->noContent();
    }
}
