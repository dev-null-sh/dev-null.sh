<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageImage>
 */
final class MessageImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'path' => 'message-images/'.fake()->uuid().'.webp',
            'width' => 1200,
            'height' => 800,
            'position' => 0,
        ];
    }
}
