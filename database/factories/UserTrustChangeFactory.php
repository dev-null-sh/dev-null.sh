<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserTrustChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTrustChange>
 */
final class UserTrustChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'is_trusted' => true,
        ];
    }
}
