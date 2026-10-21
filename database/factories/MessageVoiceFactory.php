<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageVoice;
use App\Services\VoiceNotes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageVoice>
 */
final class MessageVoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory()->state(['body' => '']),
            'path' => 'message-voices/'.fake()->uuid(),
            'duration_ms' => 4000,
            'waveform' => array_fill(0, VoiceNotes::BARS, 50),
        ];
    }
}
