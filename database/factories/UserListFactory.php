<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserList>
 */
final class UserListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
        ];
    }

    public function pinned(): self
    {
        return $this->state(fn (): array => ['pinned_at' => now()]);
    }
}
