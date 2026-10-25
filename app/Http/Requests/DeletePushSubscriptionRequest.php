<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeletePushSubscriptionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->input('user_id') === $user->id ? Response::allow() : Response::deny('Your signed-in account changed.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['endpoint' => ['required', 'string', 'max:2048']];
    }
}
