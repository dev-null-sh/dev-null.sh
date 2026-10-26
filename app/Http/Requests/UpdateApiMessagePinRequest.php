<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateApiMessagePinRequest extends FormRequest
{
    /**
     * Pinning from the app is checked as it is on the web.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return UpdateMessagePinRequest::authorization($user, $message);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
