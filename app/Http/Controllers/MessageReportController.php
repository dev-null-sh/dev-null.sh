<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateMessageReportDismissedAt;
use App\Http\Requests\DeleteMessageReportRequest;
use App\Models\MessageReport;
use Illuminate\Http\RedirectResponse;

final readonly class MessageReportController
{
    public function destroy(DeleteMessageReportRequest $request, MessageReport $report, UpdateMessageReportDismissedAt $action): RedirectResponse
    {
        $action->handle($report);

        return back();
    }
}
