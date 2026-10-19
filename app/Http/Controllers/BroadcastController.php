<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBroadcast;
use App\Actions\UpdateBroadcast;
use App\Actions\UpdateConversationReadThrough;
use App\Enums\BroadcastRole;
use App\Http\Requests\CreateBroadcastRequest;
use App\Http\Requests\ListBroadcastsRequest;
use App\Http\Requests\ShowBroadcastRequest;
use App\Http\Requests\UpdateBroadcastRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\BroadcastsQuery;
use App\Queries\ConversationsQuery;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BroadcastController
{
    /**
     * The directory of broadcasts: public ones for anyone with remotes on, and the invite-only ones the viewer
     * already belongs to or is invited to.
     */
    public function index(ListBroadcastsRequest $request, #[CurrentUser] User $user, BroadcastsQuery $broadcasts): JsonResponse
    {
        return response()->json($broadcasts->directory($user, $request->term(), $request->beforeId()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateBroadcastRequest $request, #[CurrentUser] User $user, CreateBroadcast $action): JsonResponse
    {
        $broadcast = $action->handle($user, $request->name(), $request->visibility());

        return response()->json(['broadcast' => ['id' => $broadcast->id]], 201);
    }

    /**
     * A member's thread is read before it is marked read, so the page can show where they left off; anyone else
     * gets the broadcast itself, to subscribe from.
     */
    public function show(ShowBroadcastRequest $request, #[CurrentUser] User $user, Conversation $broadcast, ConversationsQuery $conversations, ConversationThreadQuery $threads, BroadcastsQuery $queries, UpdateConversationReadThrough $action): Response
    {
        $summary = $queries->summary($user, $broadcast);
        $thread = BroadcastRole::tryFrom($summary['membership']) instanceof BroadcastRole ? $threads->broadcast($user, $broadcast) : null;

        if ($thread !== null) {
            $action->handle($user, $broadcast, $thread['messages']->last()->id ?? 0);
        }

        return Inertia::render('conversation/index', [
            'inbox' => fn (): array => $conversations->get($user),
            'partner' => null,
            'broadcast' => $summary,
            'thread' => $thread,
        ]);
    }

    public function update(UpdateBroadcastRequest $request, Conversation $broadcast, UpdateBroadcast $action): HttpResponse
    {
        $action->handle($broadcast, $request->name(), $request->visibility());

        return response()->noContent();
    }
}
