<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ModerationRule;
use App\Models\User;
use App\Services\TypeSafe;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PreviewModerationRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:280'],
            'parent_body' => ['nullable', 'string', 'max:280'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(TypeSafe $typeSafe): array
    {
        return [
            function (Validator $validator) use ($typeSafe): void {
                if (! $typeSafe->isConfigured()) {
                    $validator->errors()->add('body', __('Set TYPESAFE_API_KEY to try the rules.'));
                } elseif (! ModerationRule::query()->active()->exists()) {
                    $validator->errors()->add('body', __('Put a rule in Test or Live mode to try it.'));
                }
            },
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
