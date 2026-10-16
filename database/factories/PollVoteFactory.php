<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollVote>
 */
final class PollVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_option_id' => PollOption::factory(),
            'poll_id' => fn (array $attributes): string => PollOption::query()->whereKey($attributes['poll_option_id'])->sole()->poll_id,
            'user_id' => User::factory(),
        ];
    }
}
