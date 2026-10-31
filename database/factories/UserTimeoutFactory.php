<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationReason;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTimeout>
 */
final class UserTimeoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_id' => fn (): string => User::factory()->create()->github_id,
            'reason' => ModerationReason::Spam,
            'ends_at' => now()->addDay(),
        ];
    }

    public function ended(): self
    {
        return $this->state(fn (array $attributes): array => [
            'ends_at' => now()->subMinute(),
        ]);
    }
}
