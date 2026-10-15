<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreatePushSubscriptionRequest;
use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class PushSubscriptionController
{
    public function store(CreatePushSubscriptionRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->updatePushSubscription(
            $request->string('endpoint')->value(),
            $request->string('keys.p256dh')->value(),
            $request->string('keys.auth')->value(),
            $request->string('content_encoding', 'aes128gcm')->value(),
        );

        return back();
    }

    public function destroy(DeletePushSubscriptionRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->deletePushSubscription($request->string('endpoint')->value());

        return back();
    }
}
