<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Admins and members alike may post as their organization, by its handle, whatever its case.
 */
final readonly class OrganizationToPostAs implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public static function of(User $user, mixed $handle): ?Organization
    {
        return is_string($handle) ? $user->organizations()->whereRelation('profile', 'handle', $handle)->first() : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::of($this->user, $value) instanceof Organization) {
            $fail('You can only post as an organization you are a member of.');
        }
    }
}
