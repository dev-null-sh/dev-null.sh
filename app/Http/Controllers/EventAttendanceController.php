<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEventAttendance;
use App\Actions\DeleteEventAttendance;
use App\Http\Requests\CreateEventAttendanceRequest;
use App\Http\Requests\DeleteEventAttendanceRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class EventAttendanceController
{
    public function store(CreateEventAttendanceRequest $request, #[CurrentUser] User $user, Commit $commit, CreateEventAttendance $action): RedirectResponse
    {
        $action->handle($user, $request->event());

        return back();
    }

    public function destroy(DeleteEventAttendanceRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteEventAttendance $action): RedirectResponse
    {
        $action->handle($user, $request->event());

        return back();
    }
}
