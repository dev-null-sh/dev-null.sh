<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateRepoPet
{
    /**
     * @param  array{repository: string, repository_id: int, workflow_id: int, workflow: string}|null  $configuration
     */
    public function handle(User $user, ?array $configuration): void
    {
        $user->update(['repo_pet' => $configuration]);
    }
}
