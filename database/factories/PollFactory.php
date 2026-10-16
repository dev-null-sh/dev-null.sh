<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
final class PollFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'duration' => PollDuration::OneDay,
            'opened_at' => now(),
        ];
    }

    public function withOptions(string ...$labels): self
    {
        return $this->afterCreating(function (Poll $poll) use ($labels): void {
            foreach (array_values($labels) as $position => $label) {
                $poll->options()->create(['label' => $label, 'position' => $position]);
            }
        });
    }

    public function unopened(): self
    {
        return $this->state(fn (array $attributes): array => [
            'opened_at' => null,
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'opened_at' => now()->subMinutes(PollDuration::OneDay->value + 1),
        ]);
    }
}
