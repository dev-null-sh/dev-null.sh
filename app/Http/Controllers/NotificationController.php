<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('notifications/index', [
            'notifications' => Inertia::scroll(fn () => $user->notifications()->latest()->latest('id')->cursorPaginate(30)),
        ]);
    }

    public function show(#[CurrentUser] User $user, string $notification): RedirectResponse
    {
        // Scoped to the user, so someone else's notification is a 404.
        $notification = $user->notifications()->findOrFail($notification);
        $notification->markAsRead();

        /** @var array{user: array{username: string}} $data */
        $data = $notification->data;

        return to_route('user.show', $data['user']['username']);
    }

    public function destroy(#[CurrentUser] User $user, string $notification): RedirectResponse
    {
        $user->notifications()->findOrFail($notification)->delete();

        return back();
    }
}
