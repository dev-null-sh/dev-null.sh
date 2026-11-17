<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guild;
use App\Models\GuildReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuildReport>
 */
final class GuildReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'reporter_id' => User::factory(),
            'details' => fake()->sentence(),
        ];
    }
}
