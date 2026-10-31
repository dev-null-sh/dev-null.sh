<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateConversationCall;
use App\Actions\DeleteConversationCall;
use App\Actions\UpdateConversationCall;
use App\Http\Requests\CreateApiConversationCallRequest;
use App\Http\Requests\DeleteApiConversationCallRequest;
use App\Http\Requests\ShowApiConversationCallRequest;
use App\Http\Requests\UpdateApiConversationCallRequest;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class ConversationCallController
{
    /**
     * The user's call to the partner while it rings, with the partner's answer once they accept, as the web polls it.
     */
    public function show(ShowApiConversationCallRequest $request, #[CurrentUser] User $user, User $partner, CallQuery $calls): JsonResponse
    {
        return response()->json(['call' => $calls->outgoing($user, $partner)])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Rings the partner with the app's offer, which works the same whether they answer on the web or in the app.
     */
    public function store(CreateApiConversationCallRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->string('sdp')->value());

        return response()->noContent();
    }

    /**
     * Answers the partner's call with the app's answer.
     */
    public function update(UpdateApiConversationCallRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->call(), $request->string('sdp')->value());

        return response()->noContent();
    }

    /**
     * Ends the call between the two, whichever of them rang, as the web does.
     */
    public function destroy(DeleteApiConversationCallRequest $request, #[CurrentUser] User $user, User $partner, DeleteConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->unanswered());

        return response()->noContent();
    }
}
