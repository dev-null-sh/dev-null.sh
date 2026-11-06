<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Headline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Headline>
 */
final class HeadlineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(4),
        ];
    }
}
