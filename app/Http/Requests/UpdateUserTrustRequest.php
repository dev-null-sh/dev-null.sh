<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserTrustRequest extends FormRequest
{
    public function authorize(): bool
    {
        $moderator = $this->user();
        assert($moderator instanceof User);
        $user = $this->route('user');
        assert($user instanceof User);

        return $moderator->isAdmin() && ! $user->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_trusted' => ['required', 'boolean'],
        ];
    }
}
