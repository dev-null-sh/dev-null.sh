<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAgendaItem>
 */
final class EventAgendaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => fake()->sentence(4),
            'starts_at' => now()->addDays(7)->startOfHour(),
        ];
    }
}
