<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrgMessageRequest extends FormRequest
{
    /**
     * Only the author's own message, only within its edit window, and only where they still belong.
     */
    public static function authorization(User $user, Message $message, Conversation $org): Response
    {
        return match (true) {
            ! $user->remotes_enabled || $message->event !== null || ! $message->isVisibleTo($user) || $message->conversation_id !== $org->id || ! ($org->roleOf($user) instanceof OrgRole) => Response::denyAsNotFound(),
            $message->user_id !== $user->id => Response::denyAsNotFound(),
            $message->voice !== null => Response::deny('Voice notes cannot be edited.'),
            $message->editExpired() => Response::deny('Messages can only be edited for 3 minutes after pushing.'),
            default => Response::allow(),
        };
    }

    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        $message = $this->route('message');
        assert($message instanceof Message);

        return self::authorization($user, $message, $org);
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
