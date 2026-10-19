<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RemoteFollower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemoteFollower>
 */
final class RemoteFollowerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'user_id' => User::factory(),
            'actor_url' => "https://mastodon.example/users/{$username}",
            'inbox_url' => "https://mastodon.example/users/{$username}/inbox",
            'shared_inbox_url' => 'https://mastodon.example/inbox',
        ];
    }
}
