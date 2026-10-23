<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommitVideo> */
final class CommitVideoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'size' => 1, 'status' => CommitVideoStatus::Uploading];
    }
}
