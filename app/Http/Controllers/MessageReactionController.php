<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMessageReaction;
use App\Actions\DeleteMessageReaction;
use App\Http\Requests\CreateMessageReactionRequest;
use App\Http\Requests\DeleteMessageReactionRequest;
use App\Http\Requests\ListMessageReactionsRequest;
use App\Models\Message;
use App\Models\User;
use App\Queries\ReactionUsageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MessageReactionController
{
    public function index(ListMessageReactionsRequest $request, #[CurrentUser] User $user, ReactionUsageQuery $usage): JsonResponse
    {
        return response()->json(['usage' => $usage->get($user)])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateMessageReactionRequest $request, #[CurrentUser] User $user, Message $message, CreateMessageReaction $action): JsonResponse
    {
        $action->handle($user, $message, $request->string('emoji')->value());

        return response()->json(['reactions' => $message->reactions()->get()]);
    }

    public function destroy(DeleteMessageReactionRequest $request, #[CurrentUser] User $user, Message $message, DeleteMessageReaction $action): JsonResponse
    {
        $action->handle($user, $message, $request->string('emoji')->value());

        return response()->json(['reactions' => $message->reactions()->get()]);
    }
}
