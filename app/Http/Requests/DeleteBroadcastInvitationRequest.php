<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteBroadcastInvitationRequest extends FormRequest
{
    /**
     * The owner takes an invitation back, or the person it was for declines it. To anyone else the invitation is
     * not there.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $invitee = $this->route('invitee');
        assert($invitee instanceof User);

        return $user->remotes_enabled
            && BroadcastInvitation::query()->where('conversation_id', $broadcast->id)->where('user_id', $invitee->id)->exists()
            && ($user->is($invitee) || $broadcast->activeBroadcastRoleOf($user) === BroadcastRole::Owner)
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
