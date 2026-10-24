<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(7)->startOfHour();

        return [
            'commit_id' => Commit::factory(),
            'title' => fake()->words(3, true),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'location' => fake()->city(),
            'url' => null,
        ];
    }

    public function live(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function ended(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(2),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'cancelled_at' => now(),
        ]);
    }
}
