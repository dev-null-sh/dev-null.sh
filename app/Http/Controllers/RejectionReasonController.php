<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRejectionReason;
use App\Actions\DeleteRejectionReason;
use App\Actions\UpdateRejectionReason;
use App\Http\Requests\CreateRejectionReasonRequest;
use App\Http\Requests\DeleteRejectionReasonRequest;
use App\Http\Requests\UpdateRejectionReasonRequest;
use App\Models\RejectionReason;
use Illuminate\Http\RedirectResponse;

final readonly class RejectionReasonController
{
    public function store(CreateRejectionReasonRequest $request, CreateRejectionReason $action): RedirectResponse
    {
        $action->handle($request->string('title')->value(), $request->string('body')->value());

        return back();
    }

    public function update(UpdateRejectionReasonRequest $request, RejectionReason $rejectionReason, UpdateRejectionReason $action): RedirectResponse
    {
        $action->handle($rejectionReason, $request->string('title')->value(), $request->string('body')->value());

        return back();
    }

    public function destroy(DeleteRejectionReasonRequest $request, RejectionReason $rejectionReason, DeleteRejectionReason $action): RedirectResponse
    {
        $action->handle($rejectionReason);

        return back();
    }
}
