<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Queries\PushAccessQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateConversationMessageRequest extends FormRequest
{
    /**
     * Only the author may reword their own message, only within its edit window, and only where both halves can
     * already see it. Editing pushes to the conversation, so it also takes what pushing does: no blocks, and a merged
     * request. Anything else reads as missing, except an expired window or an unmerged request, which say so.
     */
    public static function authorization(User $user, Message $message, User $partner, PushAccessQuery $access): Response
    {
        $conversation = Conversation::between($user, $partner);

        if (! $user->remotes_enabled || $message->event !== null || ! $message->isVisibleTo($user) || $conversation === null || $message->conversation_id !== $conversation->id) {
            return Response::denyAsNotFound();
        }

        if ($message->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        if ($message->voice !== null) {
            return Response::deny('Voice notes cannot be edited.');
        }

        if ($message->editExpired()) {
            return Response::deny('Messages can only be edited for 3 minutes after pushing.');
        }

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        return $access->get($user, $partner) === PushAccess::Open
            ? Response::allow()
            : Response::deny("You can't edit until the request is merged.");
    }

    public function authorize(PushAccessQuery $access): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return self::authorization($user, $message, $partner, $access);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // A caption can be cleared down to nothing, but only where images remain to hold the message up.
        $message = $this->route('message');
        $bodyRequired = ! ($message instanceof Message && $message->images()->exists());

        return [
            'body' => [$bodyRequired ? 'required' : 'nullable', 'string', 'max:'.Message::MAX_LENGTH],
        ];
    }
}
