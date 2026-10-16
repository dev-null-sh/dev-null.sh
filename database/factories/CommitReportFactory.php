<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitReport>
 */
final class CommitReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory(),
            'reporter_id' => User::factory(),
            'reason' => 'spam',
            'details' => null,
        ];
    }
}
