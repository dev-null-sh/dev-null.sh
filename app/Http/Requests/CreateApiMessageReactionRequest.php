<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Enums\PushAccess;
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

final class CreateApiMessageReactionRequest extends FormRequest
{
    /**
     * Reacting pushes to the conversation, so it takes what pushing does: an org role, or an open conversation with
     * the partner. Events can't be reacted to.
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
            return $conversation->roleOf($user) instanceof OrgRole ? Response::allow() : Response::denyAsNotFound();
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
