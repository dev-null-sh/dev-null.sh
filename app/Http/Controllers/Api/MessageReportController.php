<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateMessageReport;
use App\Http\Requests\CreateApiMessageReportRequest;
use App\Models\Message;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class MessageReportController
{
    /**
     * Reports are private, so there is nothing to answer with. Reporting again keeps the first report.
     */
    public function store(CreateApiMessageReportRequest $request, #[CurrentUser] User $user, Message $message, CreateMessageReport $action): Response
    {
        $action->handle($user, $message, $request->string('reason')->value(), $request->filled('details') ? $request->string('details')->value() : null);

        return response()->noContent();
    }
}
