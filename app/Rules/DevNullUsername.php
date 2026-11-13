<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Profile;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
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

        $query = User::query()->where('dev_null_username', $value);

        if ($this->user instanceof Authenticatable) {
            $query->where($this->user->getAuthIdentifierName(), '!=', $this->user->getAuthIdentifier());
        }

        $displayedHandleQuery = Profile::query()
            ->where('handle', $value)
            ->where(fn (Builder $query): Builder => $query
                ->whereHas('organization')
                ->orWhereHas('user', fn (Builder $query): Builder => $query->whereNull('dev_null_username')));

        if ($this->user instanceof User) {
            $displayedHandleQuery->where('id', '!=', $this->user->getKey());
        }

        if ($query->exists() || $displayedHandleQuery->exists()) {
            $fail('validation.unique')->translate();
        }
    }

    public static function normalize(string $username): string
    {
        return Str::lower(mb_trim($username));
    }
}
