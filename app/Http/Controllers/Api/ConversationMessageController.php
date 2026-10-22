<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversation;
use App\Actions\CreateMessage;
use App\Http\Requests\CreateApiConversationMessageRequest;
use App\Http\Requests\ListApiConversationMessagesRequest;
use App\Http\Resources\ConversationThreadResource;
use App\Http\Resources\MessageResource;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class ConversationMessageController
{
    /**
     * The latest page of the conversation with the partner, the page before a message, or everything after one with
     * the reactions changed since a version, as the web's thread polls it. Someone the user hasn't talked to yet has
     * an empty thread, which says whether a first message can go to them.
     */
    public function index(ListApiConversationMessagesRequest $request, #[CurrentUser] User $user, User $partner, ConversationThreadQuery $threads): ConversationThreadResource
    {
        return ConversationThreadResource::make([
            'partner' => $partner,
            ...$threads->get($user, $partner, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
        ]);
    }

    /**
     * Pushes a message to the partner, starting the conversation if it is the first, as the web's composer does. Sending
     * the same client_id again, as a retry does, answers with the message it already created, with a 200 instead of a 201.
     */
    public function store(CreateApiConversationMessageRequest $request, #[CurrentUser] User $user, User $partner, CreateConversation $createConversation, CreateMessage $action): MessageResource
    {
        return MessageResource::make($action->handle(
            $user,
            $createConversation->handle($user, $partner),
            $request->string('body')->value(),
            $request->string('client_id')->value(),
            $request->replyTo(),
            $request->images(),
            $request->imageDescriptions(),
            $request->linkPreview(),
        ));
    }
}
