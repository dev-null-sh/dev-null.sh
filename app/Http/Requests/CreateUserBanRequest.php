<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModerationReason;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class CreateUserBanRequest extends FormRequest
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
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ModerationReason::class)],
        ];
    }

    public function reason(): ModerationReason
    {
        return ModerationReason::from($this->string('reason')->value());
    }
}
