<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateMessage;
use App\Http\Requests\CreateApiOrgMessageRequest;
use App\Http\Requests\ListApiOrgMessagesRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\OrgThreadResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class OrgMessageController
{
    /**
     * An org's thread as the web loads and polls it, from where the user's history starts.
     */
    public function index(ListApiOrgMessagesRequest $request, #[CurrentUser] User $user, Conversation $org, ConversationThreadQuery $threads): OrgThreadResource
    {
        return OrgThreadResource::make([
            'org' => $org,
            ...$threads->org($user, $org, $request->afterId(), $request->beforeId(), $request->reactionsAfter()),
        ]);
    }

    /**
     * Pushes a message to the org, as the web's composer does, once however often its client_id is sent.
     */
    public function store(CreateApiOrgMessageRequest $request, #[CurrentUser] User $user, Conversation $org, CreateMessage $action): MessageResource
    {
        return MessageResource::make($action->handle(
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
        ));
    }
}
