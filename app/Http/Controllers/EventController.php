<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateEvent;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class EventController
{
    public function update(UpdateEventRequest $request, Commit $commit, UpdateEvent $action): RedirectResponse
    {
        $action->handle($request->event(), $request->details(), $request->cover());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated. People going will hear about it.')]);

        return back();
    }
}
