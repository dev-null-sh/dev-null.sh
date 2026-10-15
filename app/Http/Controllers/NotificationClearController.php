<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class NotificationClearController
{
    public function destroy(#[CurrentUser] User $user): RedirectResponse
    {
        $user->notifications()->delete();

        return back();
    }
}
