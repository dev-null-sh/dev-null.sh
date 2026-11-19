<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContributorHelp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

final class ListContributorRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Enum|In>> */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(ContributorHelp::class)],
            'status' => ['sometimes', 'required', Rule::in(['open', 'filled', 'all'])],
        ];
    }
}
