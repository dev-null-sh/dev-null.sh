<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationReason;
use App\Models\BanEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BanEvent>
 */
final class BanEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_id' => (string) fake()->unique()->numberBetween(1, 999_999_999),
            'reason' => ModerationReason::Spam,
        ];
    }
}
