<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PushEndpoint;
use Illuminate\Foundation\Http\FormRequest;

final class CreatePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled && config('services.web_push.public_key') && config('services.web_push.private_key');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['endpoint' => ['required', 'string', 'max:2048', new PushEndpoint]];
    }
}
