<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\Mentions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class MentionedUsersQuery
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * @return array<string, string>
     */
    public function get(string $body): array
    {
        $handles = $this->mentions->handles($body);

        if ($handles === []) {
            return [];
        }

        return User::query()
            ->whereHas('profile', fn (Builder $query): Builder => $query->whereIn(DB::raw('LOWER(handle)'), $handles))
            ->get()
            ->mapWithKeys(fn (User $user): array => [Str::lower($user->handle) => $user->id])
            ->all();
    }
}
