<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventResponse;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendee>
 */
final class EventAttendeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'response' => EventResponse::Going,
        ];
    }

    public function interested(): self
    {
        return $this->state(fn (array $attributes): array => [
            'response' => EventResponse::Interested,
        ]);
    }
}
