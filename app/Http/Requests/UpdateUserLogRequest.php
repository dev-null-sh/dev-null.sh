<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Log;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateUserLogRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Enum|string>>
     */
    public function rules(): array
    {
        return [
            'log' => ['required', Rule::enum(Log::class)],
        ];
    }

    public function log(): Log
    {
        return Log::from($this->string('log')->value());
    }
}
