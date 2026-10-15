<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateModerationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fields are optional so the mode and strictness toggles can each change just one.
     *
     * @return array<string, array<int, string|ValidationRule|object>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'matches' => ['sometimes', 'required', 'string', 'max:500'],
            'does_not_match' => ['sometimes', 'nullable', 'string', 'max:500'],
            'action' => ['sometimes', 'required', Rule::enum(ModerationRuleAction::class)],
            'strictness' => ['sometimes', 'required', Rule::enum(ModerationStrictness::class)],
            'mode' => ['sometimes', 'required', Rule::enum(ModerationRuleMode::class)],
            'rejection_message' => ['sometimes', 'nullable', 'string', 'max:280'],
        ];
    }
}
