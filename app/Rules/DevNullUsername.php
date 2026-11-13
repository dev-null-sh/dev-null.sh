<?php

declare(strict_types=1);

namespace App\Rules;

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

        $query = User::query()->where('dev_null_username', $value);

        if ($this->user instanceof Authenticatable) {
            $query->where($this->user->getAuthIdentifierName(), '!=', $this->user->getAuthIdentifier());
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }

    public static function normalize(string $username): string
    {
        return Str::lower(mb_trim($username));
    }
}
