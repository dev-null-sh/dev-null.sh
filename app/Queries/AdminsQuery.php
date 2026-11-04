<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class AdminsQuery
{
    /**
     * The admins' ids, who hear whenever what waits for moderation changes.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        /** @var list<string> $admins */
        $admins = config('app.admins');

        return User::query()
            ->whereRelation('profile', fn (Builder $query): Builder => $query->whereIn(DB::raw('lower(handle)'), array_map(mb_strtolower(...), $admins)))
            ->get(['id'])
            ->map(fn (User $user): string => $user->id)
            ->all();
    }
}
