<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConversationCall;
use App\Actions\DeleteConversationCall;
use App\Actions\UpdateConversationCall;
use App\Http\Requests\CreateConversationCallRequest;
use App\Http\Requests\DeleteConversationCallRequest;
use App\Http\Requests\ShowConversationCallRequest;
use App\Http\Requests\UpdateConversationCallRequest;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class ConversationCallController
{
    public function show(ShowConversationCallRequest $request, #[CurrentUser] User $user, User $partner, CallQuery $calls): JsonResponse
    {
        return response()->json(['call' => $calls->outgoing($user, $partner)])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateConversationCallRequest $request, #[CurrentUser] User $user, User $partner, CreateConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->string('sdp')->value());

        return response()->noContent();
    }

    public function update(UpdateConversationCallRequest $request, #[CurrentUser] User $user, User $partner, UpdateConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->call(), $request->string('sdp')->value());

        return response()->noContent();
    }

    public function destroy(DeleteConversationCallRequest $request, #[CurrentUser] User $user, User $partner, DeleteConversationCall $action): Response
    {
        $action->handle($user, $partner, $request->unanswered());

        return response()->noContent();
    }
}
