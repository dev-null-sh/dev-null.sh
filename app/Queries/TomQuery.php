<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateTomFriendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class TomQuery
{
    public function get(): ?User
    {
        return User::query()
            ->where('github_id', CreateTomFriendship::GITHUB_ID)
            ->whereRelation('profile', fn (Builder $query): Builder => $query->whereIn('handle', [CreateTomFriendship::HANDLE, CreateTomFriendship::FALLBACK_HANDLE]))
            ->first();
    }
}
