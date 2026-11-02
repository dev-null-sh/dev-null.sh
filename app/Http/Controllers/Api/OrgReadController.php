<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConversationReadThrough;
use App\Http\Requests\UpdateApiOrgReadRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgReadController
{
    /**
     * Pulls the org through a message, as the web does once the thread is scrolled to it.
     */
    public function update(UpdateApiOrgReadRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationReadThrough $action): Response
    {
        $action->handle($user, $org, $request->integer('through'));

        return response()->noContent();
    }
}
