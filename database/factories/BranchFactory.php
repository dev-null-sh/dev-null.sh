<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::lower(fake()->unique()->lexify('??????')),
            'description' => null,
            'domain' => null,
            'verification_token' => null,
            'verification_requested_at' => null,
            'verified_at' => null,
            'verified_domain' => null,
        ];
    }

    public function verified(string $domain = 'example.com'): self
    {
        return $this->state(fn (array $attributes): array => [
            'domain' => $domain,
            'verified_domain' => $domain,
            'verified_at' => now(),
            'verification_token' => Str::random(32),
        ]);
    }
}
