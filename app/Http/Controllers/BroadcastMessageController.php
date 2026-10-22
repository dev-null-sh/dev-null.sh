<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Http\Requests\CreateBroadcastMessageRequest;
use App\Http\Requests\ListBroadcastMessagesRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class BroadcastMessageController
{
    public function index(ListBroadcastMessagesRequest $request, #[CurrentUser] User $user, Conversation $broadcast, ConversationThreadQuery $threads): JsonResponse
    {
        return response()->json([
            'broadcast' => ['id' => $broadcast->id, 'name' => $broadcast->name, 'visibility' => $broadcast->visibility],
            ...$threads->broadcast($user, $broadcast, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateBroadcastMessageRequest $request, #[CurrentUser] User $user, Conversation $broadcast, CreateMessage $action): JsonResponse
    {
        $message = $action->handle(
            $user,
            $broadcast,
            $request->string('body')->value(),
            $request->string('client_id')->value(),
            $request->replyTo(),
            $request->images(),
            $request->imageDescriptions(),
            $request->linkPreview(),
        );

        return response()->json(['message' => $message], 201);
    }
}
