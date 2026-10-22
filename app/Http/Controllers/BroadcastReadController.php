<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationReadThrough;
use App\Http\Requests\UpdateBroadcastReadRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class BroadcastReadController
{
    public function update(UpdateBroadcastReadRequest $request, #[CurrentUser] User $user, Conversation $broadcast, UpdateConversationReadThrough $action): Response
    {
        $action->handle($user, $broadcast, $request->integer('through'));

        return response()->noContent();
    }
}
