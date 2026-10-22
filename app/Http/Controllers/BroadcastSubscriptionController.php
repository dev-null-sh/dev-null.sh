<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBroadcastSubscription;
use App\Actions\DeleteBroadcastSubscription;
use App\Http\Requests\CreateBroadcastSubscriptionRequest;
use App\Http\Requests\DeleteBroadcastSubscriptionRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class BroadcastSubscriptionController
{
    public function store(CreateBroadcastSubscriptionRequest $request, #[CurrentUser] User $user, Conversation $broadcast, CreateBroadcastSubscription $action): Response
    {
        $action->handle($user, $broadcast);

        return response()->noContent();
    }

    public function destroy(DeleteBroadcastSubscriptionRequest $request, #[CurrentUser] User $user, Conversation $broadcast, DeleteBroadcastSubscription $action): Response
    {
        $action->handle($user, $broadcast);

        return response()->noContent();
    }
}
