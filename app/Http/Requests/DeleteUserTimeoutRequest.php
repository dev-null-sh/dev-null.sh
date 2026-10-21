<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserTimeoutRequest extends FormRequest
{
    /**
     * Only admins moderate people, and never each other.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $target = $this->route('user');
        assert($target instanceof User);

        return $user->isAdmin() && ! $target->isAdmin();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
