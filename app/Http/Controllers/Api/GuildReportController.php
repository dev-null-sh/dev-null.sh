<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateGuildReport;
use App\Http\Requests\CreateApiGuildReportRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class GuildReportController
{
    /**
     * Reports are private, so there is nothing to answer with. Reporting a guild again keeps the first report until an
     * admin dismisses it.
     */
    public function store(CreateApiGuildReportRequest $request, Guild $guild, #[CurrentUser] User $user, CreateGuildReport $action): Response
    {
        $action->handle($user, $guild, $request->string('details')->value());

        return response()->noContent();
    }
}
