<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Dto\SshPublicKey;
use App\Enums\PushPlatform;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
final class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fingerprint' => 'SHA256:'.mb_rtrim(base64_encode(random_bytes(32)), '='),
            'name' => 'devnull on iPhone',
            'approved_at' => now(),
        ];
    }

    public function withPublicKey(SshPublicKey $publicKey): self
    {
        return $this->state(fn (array $attributes): array => [
            'fingerprint' => $publicKey->fingerprint(),
        ]);
    }

    public function withPushToken(?string $token = null, PushPlatform $platform = PushPlatform::Ios): self
    {
        return $this->state(function (array $attributes) use ($token, $platform): array {
            $token ??= 'fcm-'.Str::random(40);

            return ['push_token_hash' => hash('sha256', $token), 'push_token' => $token, 'push_platform' => $platform];
        });
    }
}
