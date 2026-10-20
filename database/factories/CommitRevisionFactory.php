<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\CommitRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitRevision>
 */
final class CommitRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
