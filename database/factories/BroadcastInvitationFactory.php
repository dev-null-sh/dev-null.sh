<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastInvitation>
 */
final class BroadcastInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
        ];
    }
}
