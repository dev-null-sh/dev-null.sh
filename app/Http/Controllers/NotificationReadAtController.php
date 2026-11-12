<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateNotificationsReadAt;
use App\Http\Requests\UpdateNotificationReadAtRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class NotificationReadAtController
{
    public function update(UpdateNotificationReadAtRequest $request, #[CurrentUser] User $user, UpdateNotificationsReadAt $action): Response
    {
        $action->handle($user, $request->through());

        return response()->noContent();
    }
}
