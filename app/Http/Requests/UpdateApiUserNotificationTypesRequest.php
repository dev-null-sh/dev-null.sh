<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateApiUserNotificationTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The kinds to see, as the notifications list takes them in `?type=`.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', ListNotificationsRequest::TYPE_RULE],
        ];
    }
}
