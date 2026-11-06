<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateApiBroadcastAuthorizationRequest extends FormRequest
{
    /**
     * Only a user's own channel can be joined. Joining is checked once, so nothing that a later change could take
     * away is ever sent on it: every update only says what to read again, and reading checks access each time.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->string('channel_name')->value() === 'private-users.'.$user->id ? Response::allow() : Response::deny();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'socket_id' => ['required', 'string', 'regex:/\A\d+\.\d+\z/'],
            'channel_name' => ['required', 'string', 'max:200'],
        ];
    }
}
