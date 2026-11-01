<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Feature;
use App\Models\Organization;
use App\Models\User;
use App\Services\Features;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Admins and members alike may post as their organization, by its handle, whatever its case, once administrators
 * switch the feature on.
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
        if (! resolve(Features::class)->enabled(Feature::OrganizationCommits)) {
            $fail("Posting as an organization isn't available yet.");

            return;
        }

        if (! self::of($this->user, $value) instanceof Organization) {
            $fail('You can only post as an organization you are a member of.');
        }
    }
}
