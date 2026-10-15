<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePushSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url:https', 'max:1024'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ];
    }
}
