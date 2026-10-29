<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\MergeConflict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MergeConflict>
 */
final class MergeConflictFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'ours_id' => fn (array $attributes): string => Commit::factory()->create(['parent_id' => $attributes['commit_id']])->id,
            'theirs_id' => fn (array $attributes): string => Commit::factory()->create(['parent_id' => $attributes['commit_id']])->id,
            'closes_at' => now()->addHours(MergeConflict::VotingHours),
        ];
    }

    public function closed(): self
    {
        return $this->state(['closes_at' => now()->subMinute()]);
    }
}
