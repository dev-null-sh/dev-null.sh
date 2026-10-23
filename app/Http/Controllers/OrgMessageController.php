<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMessage;
use App\Http\Requests\CreateOrgMessageRequest;
use App\Http\Requests\ListOrgMessagesRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrgMessageController
{
    public function index(ListOrgMessagesRequest $request, #[CurrentUser] User $user, Conversation $org, ConversationThreadQuery $threads): JsonResponse
    {
        return response()->json([
            'org' => ['id' => $org->id, 'name' => $org->name],
            ...$threads->org($user, $org, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
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

        return response()->json(['message' => $message], 201);
    }
}
