<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class CreateUserKeyPair
{
    public function handle(User $user): User
    {
        if ($user->public_key !== null && $user->private_key !== null) {
            return $user;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        assert(is_array($details) && is_string($details['key']) && is_string($privateKey));

        $user->update(['public_key' => $details['key'], 'private_key' => $privateKey]);

        return $user;
    }
}
