<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrg;
use App\Actions\UpdateConversationReadThrough;
use App\Actions\UpdateOrgName;
use App\Http\Requests\CreateOrgRequest;
use App\Http\Requests\ShowOrgRequest;
use App\Http\Requests\UpdateOrgRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\ConversationThreadQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrgController
{
    /**
     * The thread is read before it is marked read, so the page can show where the user left off. Only rendering the
     * thread marks it read, so the badge poll's partial reload leaves the read receipt where the user left it.
     */
    public function show(ShowOrgRequest $request, #[CurrentUser] User $user, Conversation $org, ConversationsQuery $conversations, ConversationThreadQuery $threads, UpdateConversationReadThrough $action): Response
    {
        return Inertia::render('conversation/index', [
            'thread' => function () use ($user, $org, $threads, $action): array {
                $thread = $threads->org($user, $org);

                $action->handle($user, $org, $thread['messages']->last()->id ?? 0);

                return $thread;
            },
            'inbox' => fn (): array => $conversations->get($user),
            'partner' => null,
            'org' => ['id' => $org->id, 'name' => $org->name],
        ]);
    }

    public function store(CreateOrgRequest $request, #[CurrentUser] User $user, CreateOrg $action): JsonResponse
    {
        $org = $action->handle($user, $request->string('name')->trim()->value() ?: null, $request->members());

        return response()->json(['org' => ['id' => $org->id]], 201);
    }

    public function update(UpdateOrgRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateOrgName $action): HttpResponse
    {
        $action->handle($user, $org, $request->string('name')->trim()->value() ?: null);

        return response()->noContent();
    }
}
