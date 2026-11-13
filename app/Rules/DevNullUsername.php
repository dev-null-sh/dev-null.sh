<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Profile;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final readonly class DevNullUsername implements ValidationRule
{
    public function __construct(private ?Authenticatable $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){0,38}$/', $value)) {
            $fail('validation.regex')->translate();

            return;
        }

        if ($this->user instanceof User && self::normalize($this->user->handle) === $value) {
            $fail('The Dev-Null username must be different from your GitHub username.');

            return;
        }

        if (self::isClaimedByAnotherAccount($value, $this->user)) {
            $fail('validation.unique')->translate();
        }
    }

    public static function isClaimedByAnotherAccount(string $username, ?Authenticatable $user): bool
    {
        $userQuery = User::query()->where('dev_null_username', $username);
        $profileQuery = Profile::query()->where('handle', $username);

        if ($user instanceof Authenticatable) {
            $userQuery->where($user->getAuthIdentifierName(), '!=', $user->getAuthIdentifier());
        }

        if ($user instanceof User) {
            $profileQuery->where('id', '!=', $user->getKey());
        }

        return $userQuery->exists() || $profileQuery->exists();
    }

    public static function normalize(string $username): string
    {
        return Str::lower(mb_trim($username));
    }
}
