<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\SshKey;

/**
 * Finds the username of the user who owns an SSH key mirrored from GitHub, by the key's fingerprint.
 */
final readonly class SshKeyOwnerQuery
{
    public function find(string $fingerprint): ?string
    {
        $username = SshKey::query()
            ->join('users', 'users.id', '=', 'ssh_keys.user_id')
            ->where('ssh_keys.fingerprint', $fingerprint)
            ->value('users.username');

        return is_string($username) ? $username : null;
    }
}
