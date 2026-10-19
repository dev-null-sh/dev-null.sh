<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Passkey;
use App\Models\User;
use App\Services\Passkeys;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A passkey row whose key can't sign: tests that sign register a Tests\Support\SoftwareAuthenticator instead.
 *
 * @extends Factory<Passkey>
 */
final class PasskeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => '1Password',
            'credential_id' => Passkeys::encode(random_bytes(16)),
            'credential' => ['public_key' => '', 'algorithm' => Passkeys::ES256, 'sign_count' => 0],
        ];
    }
}
