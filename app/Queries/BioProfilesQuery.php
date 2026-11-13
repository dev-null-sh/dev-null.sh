<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Profile;
use App\Models\User;
use App\Services\Mentions;
use Illuminate\Support\Str;

final readonly class BioProfilesQuery
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * The users, organizations and unclaimed developers on dev/null that the user's bio and company mention, as their handle by lowercase
     * handle, so the profile links them here rather than to GitHub.
     *
     * @return array<string, string>
     */
    public function get(User $user): array
    {
        return $this->profiles($this->handles($user));
    }

    /**
     * What get() returns for each user, by user id, with one query for the whole page.
     *
     * @param  iterable<array-key, User>  $users
     * @return array<int|string, array<string, string>>
     */
    public function getMany(iterable $users): array
    {
        $handles = collect($users)->mapWithKeys(fn (User $user): array => [$user->id => $this->handles($user)]);
        $profiles = $this->profiles(array_values(array_unique(array_merge(...$handles->values()->all()))));

        return $handles->map(fn (array $mentioned): array => array_intersect_key($profiles, array_flip($mentioned)))->all();
    }

    /**
     * @return list<string>
     */
    private function handles(User $user): array
    {
        return $this->mentions->handles("{$user->bio}\n{$user->company}");
    }

    /**
     * @param  list<string>  $handles
     * @return array<string, string>
     */
    private function profiles(array $handles): array
    {
        if ($handles === []) {
            return [];
        }

        return Profile::query()
            ->whereIn('handle', $handles)
            ->get(['handle'])
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => $profile->handle])
            ->all();
    }
}
