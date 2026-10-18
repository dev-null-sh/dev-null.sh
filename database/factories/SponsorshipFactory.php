<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsorship>
 */
final class SponsorshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsorable_id' => User::factory(),
            'sponsor_id' => User::factory(),
            'is_one_time' => false,
            'lapses_at' => null,
            'left_at' => null,
        ];
    }

    public function oneTime(): self
    {
        return $this->state(fn (): array => [
            'is_one_time' => true,
            'lapses_at' => now()->addDays(Sponsorship::ONE_TIME_DAYS),
        ]);
    }

    public function lapsed(): self
    {
        return $this->state(fn (): array => ['lapses_at' => now()->subDay()]);
    }
}
