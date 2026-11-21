<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteMessagePinRequest extends FormRequest
{
    /**
     * Maintainers unpin an org's messages for everyone in it. The app unpins the same way.
     */
    public static function authorization(User $user, Message $message): Response
    {
        $org = $message->conversation;

        return match (true) {
            ! $user->remotes_enabled || ! $message->isVisibleTo($user) || ! $org->isOrg() => Response::denyAsNotFound(),
            ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can unpin messages.'),
            default => Response::allow(),
        };
    }

    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return self::authorization($user, $message);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
