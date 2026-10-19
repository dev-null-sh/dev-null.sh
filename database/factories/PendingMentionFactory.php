<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\PendingMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingMention>
 */
final class PendingMentionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'username' => mb_strtolower(fake()->unique()->userName()),
        ];
    }
}
