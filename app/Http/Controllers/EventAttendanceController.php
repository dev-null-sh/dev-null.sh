<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteEventAttendance;
use App\Actions\UpdateEventAttendance;
use App\Http\Requests\DeleteEventAttendanceRequest;
use App\Http\Requests\UpdateEventAttendanceRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class EventAttendanceController
{
    public function update(UpdateEventAttendanceRequest $request, #[CurrentUser] User $user, Event $event, UpdateEventAttendance $action): RedirectResponse
    {
        $action->handle($user, $event, $request->eventResponse());

        return back();
    }

    public function destroy(DeleteEventAttendanceRequest $request, #[CurrentUser] User $user, Event $event, DeleteEventAttendance $action): RedirectResponse
    {
        $action->handle($user, $event);

        return back();
    }
}
