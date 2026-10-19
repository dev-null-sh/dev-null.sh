<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ContributedRepo implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->user->repos()->whereKey($value)->exists()) {
            $fail('That repo is no longer available to commit to.');
        }
    }
}
