<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiMessageReactionRequest extends FormRequest
{
    /**
     * A reaction can be taken back for as long as its message is visible.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return $user->remotes_enabled && $message->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:32'],
        ];
    }
}
