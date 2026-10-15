<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class NotificationReadController
{
    public function store(#[CurrentUser] User $user): RedirectResponse
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
