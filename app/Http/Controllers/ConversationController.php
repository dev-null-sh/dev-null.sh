<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationReadThrough;
use App\Http\Requests\ListConversationsRequest;
use App\Http\Requests\ShowConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ConversationController
{
    public function index(ListConversationsRequest $request, #[CurrentUser] User $user, ConversationsQuery $conversations): Response
    {
        return Inertia::render('conversation/index', [
            'inbox' => fn (): array => $conversations->get($user),
            'partner' => null,
            'thread' => null,
        ]);
    }

    /**
     * The thread is read before it is marked read, so the page can show where the user left off. Only rendering the
     * thread marks it read, so the badge poll's partial reload leaves the read receipt where the user left it.
     */
    public function show(ShowConversationRequest $request, #[CurrentUser] User $user, User $partner, ConversationsQuery $conversations, ConversationThreadQuery $threads, UpdateConversationReadThrough $action): Response
    {
        return Inertia::render('conversation/index', [
            'thread' => function () use ($user, $partner, $threads, $action): array {
                $thread = $threads->get($user, $partner);

                $conversation = Conversation::between($user, $partner);

                if ($conversation instanceof Conversation) {
                    $action->handle($user, $conversation, $thread['messages']->last()->id ?? 0);
                }

                return $thread;
            },
            'inbox' => fn (): array => $conversations->get($user),
            'partner' => $partner,
        ]);
    }
}
