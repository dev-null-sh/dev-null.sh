<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteMessagePin;
use App\Actions\UpdateMessagePin;
use App\Http\Requests\DeleteMessagePinRequest;
use App\Http\Requests\UpdateMessagePinRequest;
use App\Http\Resources\ShownGuildMentions;
use App\Models\Message;
use App\Models\User;
use App\Queries\PinnedMessagesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Pinning and unpinning answer with the org's pinned messages and its reactions version. The version is read before
 * the messages, as the thread reads it, so a change landing in between is handed over again on the next poll.
 */
final readonly class MessagePinController
{
    public function update(UpdateMessagePinRequest $request, Message $message, #[CurrentUser] User $user, UpdateMessagePin $action, PinnedMessagesQuery $pinnedMessages): JsonResponse
    {
        $action->handle($message);

        $org = $message->conversation()->firstOrFail();

        return response()->json(ShownGuildMentions::of(['pinned' => $pinnedMessages->get($org), 'reactions_version' => $org->reactions_version], $user));
    }

    public function destroy(DeleteMessagePinRequest $request, Message $message, #[CurrentUser] User $user, DeleteMessagePin $action, PinnedMessagesQuery $pinnedMessages): JsonResponse
    {
        $action->handle($message);

        $org = $message->conversation()->firstOrFail();

        return response()->json(ShownGuildMentions::of(['pinned' => $pinnedMessages->get($org), 'reactions_version' => $org->reactions_version], $user));
    }
}
