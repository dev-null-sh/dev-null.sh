<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Http\Requests\CreateOrgMessageRequest;
use App\Http\Requests\ListOrgMessagesRequest;
use App\Http\Resources\GuildBadges;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgMessageController
{
    public function index(ListOrgMessagesRequest $request, #[CurrentUser] User $user, Conversation $org, ConversationThreadQuery $threads): JsonResponse
    {
        $thread = $threads->org($user, $org, $request->afterId(), $request->beforeId(), $request->reactionsAfter());
        $badges = GuildBadges::forMessages([...$thread['messages'], ...$thread['pinned'] ?? [], ...$thread['mentions']], $user);

        return response()->json([
            'org' => ['id' => $org->id, 'name' => $org->name],
            ...$thread,
            'messages' => $badges->onMessages($thread['messages']),
            'pinned' => $thread['pinned'] === null ? null : $badges->onMessages($thread['pinned']),
            'mentions' => $badges->onMessages($thread['mentions']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateOrgMessageRequest $request, #[CurrentUser] User $user, Conversation $org, CreateMessage $action): JsonResponse
    {
        $message = $action->handle(
            $user,
            $org,
            $request->string('body')->value(),
            $request->string('client_id')->value(),
            $request->replyTo(),
            $request->images(),
            $request->imageDescriptions(),
            $request->linkPreview(),
            $request->voice(),
            $request->voiceWaveform(),
        );

        return response()->json(['message' => GuildBadges::forMessages([$message], $user)->onMessage($message)], 201);
    }
}
