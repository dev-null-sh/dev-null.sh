<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePushSubscription;
use App\Actions\DeletePushSubscription;
use App\Contracts\Services\PushNotificationProvider;
use App\Http\Requests\CreatePushSubscriptionRequest;
use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class PushSubscriptionController
{
    public function index(PushNotificationProvider $push): JsonResponse
    {
        return response()->json([
            'public_key' => $push->publicKey(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreatePushSubscriptionRequest $request, #[CurrentUser] User $user, CreatePushSubscription $action): Response
    {
        $subscription = $action->handle($user, $request->string('endpoint')->toString());
        $request->session()->put('push_endpoint_hash', $subscription->endpoint_hash);

        return response()->noContent();
    }

    public function destroy(DeletePushSubscriptionRequest $request, #[CurrentUser] User $user, DeletePushSubscription $action): Response
    {
        $action->handle($user, hash('sha256', $request->string('endpoint')->toString()));
        $request->session()->forget('push_endpoint_hash');

        return response()->noContent();
    }
}
