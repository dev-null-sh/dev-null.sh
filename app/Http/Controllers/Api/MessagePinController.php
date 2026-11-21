<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\DeleteMessagePin;
use App\Actions\UpdateMessagePin;
use App\Http\Requests\DeleteApiMessagePinRequest;
use App\Http\Requests\UpdateApiMessagePinRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Queries\PinnedMessagesQuery;
use Illuminate\Http\JsonResponse;

/**
 * Pinning and unpinning from the app, as the web does: each answers with the org's pinned messages, as the thread
 * hands them over, and its reactions version. The version is read before the messages, so a change landing in
 * between is handed over again on the next poll.
 */
final readonly class MessagePinController
{
    public function update(UpdateApiMessagePinRequest $request, Message $message, UpdateMessagePin $action, PinnedMessagesQuery $pinnedMessages): JsonResponse
    {
        $action->handle($message);

        $org = $message->conversation()->firstOrFail();

        return response()->json(['pinned' => MessageResource::collection($pinnedMessages->get($org)), 'reactions_version' => $org->reactions_version]);
    }

    public function destroy(DeleteApiMessagePinRequest $request, Message $message, DeleteMessagePin $action, PinnedMessagesQuery $pinnedMessages): JsonResponse
    {
        $action->handle($message);

        $org = $message->conversation()->firstOrFail();

        return response()->json(['pinned' => MessageResource::collection($pinnedMessages->get($org)), 'reactions_version' => $org->reactions_version]);
    }
}
