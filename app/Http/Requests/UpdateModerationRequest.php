<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateModerationRequest extends FormRequest
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
            'commits' => ['required', 'array', 'max:100'],
            'commits.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
