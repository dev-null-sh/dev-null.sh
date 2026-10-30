<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Services\Mentions;
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
     * so it is left out. A stale profile may still hold the same handle in another case, so the case it is written in
     * wins, then always the same one, like organization pages.
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

        $written = $this->mentions->handlesAsWritten($body);

        return Organization::query()
            ->select('organizations.*')
            ->join('profiles', 'profiles.id', '=', 'organizations.id')
            ->whereIn(DB::raw('LOWER(profiles.handle)'), $handles)
            ->orderBy('profiles.handle')
            ->get()
            ->sortByDesc(fn (Organization $organization): bool => in_array($organization->handle, $written, true))
            ->uniqueStrict(fn (Organization $organization): string => Str::lower($organization->handle))
            ->mapWithKeys(fn (Organization $organization): array => [Str::lower($organization->handle) => $organization->id])
            ->all();
    }
}
