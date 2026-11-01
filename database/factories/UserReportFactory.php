<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserReport>
 */
final class UserReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reporter_id' => User::factory(),
            'reason' => 'spam',
            'details' => null,
        ];
    }
}
