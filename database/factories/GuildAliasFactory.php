<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guild;
use App\Models\GuildAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuildAlias>
 */
final class GuildAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'handle' => fake()->unique()->lexify('alias????????'),
        ];
    }
}
