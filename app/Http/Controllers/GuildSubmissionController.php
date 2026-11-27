<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteGuildSubmission;
use App\Http\Requests\DeleteGuildSubmissionRequest;
use Illuminate\Http\RedirectResponse;

final readonly class GuildSubmissionController
{
    public function destroy(DeleteGuildSubmissionRequest $request, DeleteGuildSubmission $action): RedirectResponse
    {
        $action->handle($request->submission());

        return back();
    }
}
