<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class ListPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('cursor') || ! $this->filled('cursor')) {
                return;
            }

            $parameters = Cursor::fromEncoded($this->string('cursor')->value())?->toArray() ?? [];

            if (! is_string($parameters['id'] ?? null)
                || ! Str::isUuid($parameters['id'])
                || ! is_bool($parameters['_pointsToNextItems'] ?? null)) {
                $validator->errors()->add('cursor', 'The cursor is invalid.');
            }
        }];
    }
}
