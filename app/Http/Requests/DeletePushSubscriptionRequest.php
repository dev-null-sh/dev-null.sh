<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeletePushSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:1024'],
        ];
    }
}
