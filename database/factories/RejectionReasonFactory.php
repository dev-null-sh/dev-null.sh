<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RejectionReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RejectionReason>
 */
final class RejectionReasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, asText: true),
            'body' => fake()->sentence(),
        ];
    }
}
