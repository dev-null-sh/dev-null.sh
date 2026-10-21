<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExploreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['suppressed' => ['nullable', 'boolean']];
    }
}
