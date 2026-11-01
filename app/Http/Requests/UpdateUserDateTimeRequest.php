<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserDateTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_format' => ['present', 'nullable', Rule::enum(DateFormat::class)],
            'time_format' => ['present', 'nullable', Rule::enum(TimeFormat::class)],
        ];
    }
}
