<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MoveModerationRuleRequest extends FormRequest
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
            'direction' => ['required', 'in:up,down'],
        ];
    }

    public function isUp(): bool
    {
        return $this->string('direction')->toString() === 'up';
    }
}
