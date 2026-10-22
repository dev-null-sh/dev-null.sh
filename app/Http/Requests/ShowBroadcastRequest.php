<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\BroadcastsQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowBroadcastRequest extends FormRequest
{
    /**
     * Members and invitees see the broadcast; beyond them only a public one, to subscribe from. Invite-only ones look
     * like any other missing page to everyone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        return $user->remotes_enabled && $broadcast->isBroadcast()
            && ($broadcast->activeBroadcastRoleOf($user) instanceof BroadcastRole
                || $broadcast->visibility === BroadcastVisibility::Public
                || resolve(BroadcastsQuery::class)->isInvited($user, $broadcast))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
