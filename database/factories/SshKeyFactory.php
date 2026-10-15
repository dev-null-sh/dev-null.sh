<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib4\Common\Functions\Strings;

/**
 * @extends Factory<SshKey>
 */
final class SshKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicKey = SshPublicKey::fromBlob(
            Strings::packSSH2('ss', 'ssh-ed25519', sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())),
        );

        return [
            'user_id' => User::factory(),
            'fingerprint' => $publicKey->fingerprint(),
        ];
    }

    public function withPublicKey(SshPublicKey $publicKey): self
    {
        return $this->state(fn (array $attributes): array => [
            'fingerprint' => $publicKey->fingerprint(),
        ]);
    }
}
