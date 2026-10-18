<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Log;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateUserLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'log' => ['required', Rule::enum(Log::class)],
        ];
    }

    public function log(): Log
    {
        $log = $this->enum('log', Log::class);
        assert($log instanceof Log);

        return $log;
    }
}
