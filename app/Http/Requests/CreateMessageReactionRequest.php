<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushAccess;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Queries\PushAccessQuery;
use App\Rules\MessageReactionLimit;
use App\Services\EmojiShortcodes;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateMessageReactionRequest extends FormRequest
{
    /**
     * Reacting pushes to the conversation, so it takes what pushing does: a membership the org can see, or an open
     * conversation with the partner. Events can't be reacted to.
     */
    public function authorize(PushAccessQuery $access): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        if (! $user->remotes_enabled || $message->event !== null || ! $message->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        $conversation = $message->conversation;

        if ($conversation->isOrg()) {
            $membership = $conversation->membershipOf($user);

            return match (true) {
                ! $membership instanceof ConversationParticipant => Response::denyAsNotFound(),
                $membership->is_hidden => Response::deny('Show your membership to react.'),
                default => Response::allow(),
            };
        }

        $partner = $conversation->participants()->whereNot('user_id', $user->id)->sole()->user;

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        return $access->get($user, $partner) === PushAccess::Open
            ? Response::allow()
            : Response::deny("You can't react until the request is merged.");
    }

    /**
     * @return array<string, array<int, string|ValidationRule|In>>
     */
    public function rules(EmojiShortcodes $emojiShortcodes): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return [
            'emoji' => ['required', 'string', Rule::in($emojiShortcodes->emoji()), new MessageReactionLimit($message, $user)],
        ];
    }
}
