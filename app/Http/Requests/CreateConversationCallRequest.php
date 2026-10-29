<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\Services\TurnProvider;
use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\CallQuery;
use App\Queries\PushAccessQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateConversationCallRequest extends FormRequest
{
    /**
     * A call goes where a ping could: into a conversation that has started, to someone who can still be pushed to,
     * and not over another call already ringing for them.
     */
    public function authorize(PushAccessQuery $access, CallQuery $calls, TurnProvider $turn): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        if (! $turn->isAvailable()) {
            return Response::deny("Calls aren't available right now.");
        }

        if ($access->get($user, $partner) !== PushAccess::Open || ($calls->get($partner)['caller_id'] ?? $user->id) !== $user->id) {
            return Response::deny("You can't call {$partner->mention()} right now.");
        }

        return Conversation::between($user, $partner) instanceof Conversation
            ? Response::allow()
            : Response::deny("Say hi to {$partner->mention()} before calling them.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sdp' => ['required', 'string', 'starts_with:v=0', 'max:10000'],
        ];
    }
}
