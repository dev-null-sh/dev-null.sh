<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserGitHubFollowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $follower = $this->user();
        assert($follower instanceof User);

        $user = $this->route('user');
        assert($user instanceof User);

        return $follower->isNot($user) && $user->hasGitHubAccount() && ! $follower->hasBlockWith($user);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
