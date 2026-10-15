<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Models\ModerationRule;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateModerationRuleRequest extends FormRequest
{
    public const int MAX_RULES = 25;

    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * Every rule is asked about every commit, so the cap keeps the list short enough to review, not fast to run.
     *
     * @return array<string, array<int, string|ValidationRule|Closure|object>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail): void {
                if (ModerationRule::query()->count() >= self::MAX_RULES) {
                    $fail(__('You can have up to :count rules. Delete one first.', ['count' => self::MAX_RULES]));
                }
            }],
            'matches' => ['required', 'string', 'max:500'],
            'does_not_match' => ['nullable', 'string', 'max:500'],
            'action' => ['required', Rule::enum(ModerationRuleAction::class)],
            'strictness' => ['required', Rule::enum(ModerationStrictness::class)],
            'mode' => ['sometimes', Rule::enum(ModerationRuleMode::class)],
            'rejection_message' => ['nullable', 'string', 'max:280'],
        ];
    }
}
