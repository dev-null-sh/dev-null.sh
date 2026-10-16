<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitImage>
 */
final class CommitImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'path' => 'commit-images/'.fake()->uuid().'.webp',
            'width' => 1200,
            'height' => 800,
            'position' => 0,
        ];
    }
}
