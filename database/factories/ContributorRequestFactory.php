<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContributorHelp;
use App\Enums\ContributorRequestStatus;
use App\Models\Commit;
use App\Models\ContributorRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributorRequest> */
final class ContributorRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commit_id' => fn (array $attributes): string => Commit::factory()->for(User::query()->whereKey($attributes['user_id'])->firstOrFail())->create()->id,
            'project_url' => 'https://github.com/example/project',
            'kind' => ContributorHelp::Testing,
            'status' => ContributorRequestStatus::Open,
            'filled_at' => null,
        ];
    }
}
