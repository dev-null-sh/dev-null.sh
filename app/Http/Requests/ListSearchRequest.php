<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListSearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function term(): string
    {
        return $this->string('q')->trim()->value();
    }

    public function guildHandle(): ?string
    {
        return preg_match('/^#\p{L}[\p{L}\p{M}\p{N}_]{0,59}$/uD', $this->term()) === 1 ? mb_strtolower(mb_substr($this->term(), 1)) : null;
    }
}
