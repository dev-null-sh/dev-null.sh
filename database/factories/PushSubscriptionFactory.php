<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/wp/'.fake()->uuid();

        return [
            'user_id' => User::factory(),
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
        ];
    }
}
