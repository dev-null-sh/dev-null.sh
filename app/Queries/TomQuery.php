<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateTomFriendship;
use App\Models\User;

final readonly class TomQuery
{
    public function get(): ?User
    {
        return User::query()
            ->where('github_id', CreateTomFriendship::GITHUB_ID)
            ->whereIn('username', [CreateTomFriendship::USERNAME, CreateTomFriendship::FALLBACK_USERNAME])
            ->first();
    }
}
