<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\DevNullUsername;
use Illuminate\Foundation\Http\FormRequest;

final class CheckDevNullUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, string|DevNullUsername>>
     */
    public function rules(): array
    {
        return [
            'dev_null_username' => ['required', 'string', 'max:39', new DevNullUsername($this->user())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->query('dev_null_username');

        if (is_string($username)) {
            $this->merge(['dev_null_username' => DevNullUsername::normalize($username)]);
        }
    }
}
