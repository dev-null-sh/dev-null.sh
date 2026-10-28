<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserWatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $watched = $this->route('user');
        assert($watched instanceof User);

        return $user->isFollowing($watched);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
