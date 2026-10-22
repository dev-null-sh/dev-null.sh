<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateMessageReaction;
use App\Actions\DeleteMessageReaction;
use App\Http\Requests\CreateApiMessageReactionRequest;
use App\Http\Requests\DeleteApiMessageReactionRequest;
use App\Http\Requests\ListApiMessageReactionsRequest;
use App\Models\Message;
use App\Models\User;
use App\Queries\ReactionUsageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MessageReactionController
{
    /**
     * The emoji the user reacts with, most used first, which the web ranks its reaction picker by.
     */
    public function index(ListApiMessageReactionsRequest $request, #[CurrentUser] User $user, ReactionUsageQuery $usage): JsonResponse
    {
        return response()->json(['usage' => $usage->get($user)]);
    }

    /**
     * Reacts with an emoji, once however often it is sent, and answers with the message's reactions in the order they
     * were left, as the web does.
     */
    public function store(CreateApiMessageReactionRequest $request, #[CurrentUser] User $user, Message $message, CreateMessageReaction $action): JsonResponse
    {
        $action->handle($user, $message, $request->string('emoji')->value());

        return response()->json(['reactions' => $message->reactions()->get()]);
    }

    public function destroy(DeleteApiMessageReactionRequest $request, #[CurrentUser] User $user, Message $message, DeleteMessageReaction $action): JsonResponse
    {
        $action->handle($user, $message, $request->string('emoji')->value());

        return response()->json(['reactions' => $message->reactions()->get()]);
    }
}
