<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExploreTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListExploreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['tab' => ['nullable', Rule::in(['explore', 'trending', ...array_keys(ExploreTopic::CATEGORIES)])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
