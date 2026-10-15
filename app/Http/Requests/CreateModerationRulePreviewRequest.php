<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ModerationRule;
use App\Models\User;
use App\Services\TypeSafe;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class CreateModerationRulePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * Trying the rules needs a key to ask Jev with and at least one rule to ask about.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(TypeSafe $typeSafe): array
    {
        return [
            'body' => ['required', 'string', 'max:280', function (string $attribute, mixed $value, Closure $fail) use ($typeSafe): void {
                if (! $typeSafe->isConfigured()) {
                    $fail(__('Set TYPESAFE_API_KEY to try the rules.'));
                } elseif (! ModerationRule::query()->active()->exists()) {
                    $fail(__('Put a rule in Test or Live mode to try it.'));
                }
            }],
            'parent_body' => ['nullable', 'string', 'max:280'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function parentBody(): ?string
    {
        return $this->filled('parent_body') ? $this->string('parent_body')->toString() : null;
    }
}
