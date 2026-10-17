<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushAccess;
use App\Models\Message;
use App\Models\User;
use App\Queries\PushAccessQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateConversationMessageRequest extends FormRequest
{
    /**
     * A request can't be answered before it is merged, and a sender gets one message in until it is.
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

        return match ($access->get($user, $partner)) {
            PushAccess::Open => Response::allow(),
            PushAccess::Request => Response::deny("Merge @{$partner->username}'s request before replying."),
            PushAccess::Waiting => Response::deny("@{$partner->username} hasn't merged your request yet."),
            PushAccess::Restricted => Response::deny("@{$partner->username} doesn't follow you, so you can't push to them yet."),
            PushAccess::Closed => Response::deny("@{$partner->username} isn't taking remotes."),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
            'client_id' => ['required', 'uuid'],
        ];
    }
}
