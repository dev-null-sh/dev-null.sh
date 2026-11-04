<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEventCancellation;
use App\Http\Requests\CreateEventCancellationRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

final readonly class EventCancellationController
{
    public function store(CreateEventCancellationRequest $request, Event $event, CreateEventCancellation $action): RedirectResponse
    {
        $action->handle($event);

        return back();
    }
}
