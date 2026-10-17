<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateConversationReadThrough;
use App\Http\Requests\UpdateOrgReadRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgReadController
{
    public function update(UpdateOrgReadRequest $request, #[CurrentUser] User $user, Conversation $org, UpdateConversationReadThrough $action): Response
    {
        $action->handle($user, $org, $request->integer('through'));

        return response()->noContent();
    }
}
