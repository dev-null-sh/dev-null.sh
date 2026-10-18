<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Repo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Repo>
 */
final class RepoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2, false);

        return [
            'name' => Str::replace('-', ' ', $slug),
            'slug' => $slug,
            'description' => fake()->sentence(),
        ];
    }
}
