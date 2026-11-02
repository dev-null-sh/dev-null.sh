<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * An integer kept encrypted with the app key, like the encrypted cast keeps strings, so even a number such as how
 * long a voice note plays says nothing in a leaked database.
 *
 * @implements CastsAttributes<int, int>
 */
final readonly class EncryptedInteger implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int
    {
        return is_string($value) ? (int) Crypt::decryptString($value) : 0;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return Crypt::encryptString((string) $value);
    }
}
