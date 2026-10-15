<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEventCancellation;
use App\Http\Requests\CreateEventCancellationRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class EventCancellationController
{
    public function store(CreateEventCancellationRequest $request, Commit $commit, CreateEventCancellation $action): RedirectResponse
    {
        $action->handle($request->event());

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Event cancelled. People going will hear about it.')]);

        return back();
    }
}
