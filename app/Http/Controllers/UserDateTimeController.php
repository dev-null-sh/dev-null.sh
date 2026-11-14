<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserDateTime;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Http\Requests\UpdateUserDateTimeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserDateTimeController
{
    public function update(UpdateUserDateTimeRequest $request, #[CurrentUser] User $user, UpdateUserDateTime $action): RedirectResponse
    {
        $action->handle(
            $user,
            $request->enum('date_format', DateFormat::class),
            $request->enum('time_format', TimeFormat::class),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Date and time preferences saved.',
        ]);

        return back();
    }
}
