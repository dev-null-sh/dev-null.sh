<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\HumanVerificationToken;
use Illuminate\Foundation\Http\FormRequest;

final class CreateHumanVerificationRequest extends FormRequest
{
    public const string SessionKey = 'human-verified';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:2048', new HumanVerificationToken()],
        ];
    }
}
