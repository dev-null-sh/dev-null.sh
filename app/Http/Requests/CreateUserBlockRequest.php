<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $blocked = $this->route('user');
        assert($blocked instanceof User);

        return $user->isNot($blocked);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
