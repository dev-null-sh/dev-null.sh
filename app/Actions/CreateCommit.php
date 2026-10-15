<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

final readonly class CreateCommit
{
    public function handle(User $user, string $body): Commit
    {
        return $user->commits()->create([
            'body' => $body,
            'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
        ]);
    }
}
