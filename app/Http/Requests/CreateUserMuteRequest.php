<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserMuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $muted = $this->route('user');
        assert($muted instanceof User);

        return $user->isNot($muted);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
