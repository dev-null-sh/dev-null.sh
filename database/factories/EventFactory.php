<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventFormat;
use App\Enums\StreamPlatform;
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
        $startsAt = now()->addDays(10)->setTime(19, 0);

        return [
            'commit_id' => Commit::factory(),
            'title' => fake()->sentence(4),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'timezone' => 'Europe/Kyiv',
            'format' => EventFormat::Onsite,
            'venue' => 'Coworking Hall',
            'address' => '12 Market Square, Lviv',
            'platform' => null,
            'join_url' => null,
        ];
    }

    public function online(): self
    {
        return $this->state(fn (array $attributes): array => [
            'format' => EventFormat::Online,
            'venue' => null,
            'address' => null,
            'platform' => StreamPlatform::Youtube,
            'join_url' => 'https://youtube.com/live/'.fake()->lexify('??????????'),
        ]);
    }

    public function hybrid(): self
    {
        return $this->state(fn (array $attributes): array => [
            'format' => EventFormat::Hybrid,
            'platform' => StreamPlatform::Youtube,
            'join_url' => 'https://youtube.com/live/'.fake()->lexify('??????????'),
        ]);
    }

    public function live(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function past(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'cancelled_at' => now(),
            'sequence' => 1,
        ]);
    }

    public function withCover(): self
    {
        return $this->state(fn (array $attributes): array => [
            'cover_path' => 'event-covers/'.fake()->uuid().'.webp',
            'cover_width' => 1600,
            'cover_height' => 900,
        ]);
    }
}
