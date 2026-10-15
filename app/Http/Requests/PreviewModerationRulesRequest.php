<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewModerationRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function parentBody(): ?string
    {
        return $this->filled('parent_body') ? $this->string('parent_body')->toString() : null;
    }
}
