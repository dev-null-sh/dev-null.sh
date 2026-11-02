<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\PushAccessQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateConversationPingRequest extends FormRequest
{
    /**
     * A ping goes where a message could, but never opens a conversation: someone has to say something first.
     */
    public function authorize(PushAccessQuery $access): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        if ($access->get($user, $partner) !== PushAccess::Open) {
            return Response::deny("You can't ping {$partner->mention()} right now.");
        }

        return Conversation::between($user, $partner) instanceof Conversation
            ? Response::allow()
            : Response::deny("Say hi to {$partner->mention()} before pinging them.");
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
