<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Profile;
use App\Models\User;
use App\Services\Mentions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class BioProfilesQuery
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * The users and organizations on dev/null that the user's bio and company mention, as their handle by lowercase
     * handle, so the profile links them here rather than to GitHub.
     *
     * @return array<string, string>
     */
    public function get(User $user): array
    {
        $handles = $this->mentions->handles("{$user->bio}\n{$user->company}");

        if ($handles === []) {
            return [];
        }

        return Profile::query()
            ->whereIn(DB::raw('LOWER(handle)'), $handles)
            ->get(['handle'])
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => $profile->handle])
            ->all();
    }
}
