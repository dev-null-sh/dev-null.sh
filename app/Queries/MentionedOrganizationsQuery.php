<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Services\Mentions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class MentionedOrganizationsQuery
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * The organizations mentioned in the text, by lowercase handle. A handle that is also a user's mentions the user,
     * so it is left out.
     *
     * @param  array<string, string>  $mentionedUsers
     * @return array<string, string>
     */
    public function get(string $body, array $mentionedUsers): array
    {
        $handles = array_values(array_diff($this->mentions->handles($body), array_keys($mentionedUsers)));

        if ($handles === []) {
            return [];
        }

        return Organization::query()
            ->whereHas('profile', fn (Builder $query): Builder => $query->whereIn(DB::raw('LOWER(handle)'), $handles))
            ->get()
            ->mapWithKeys(fn (Organization $organization): array => [Str::lower($organization->handle) => $organization->id])
            ->all();
    }
}
