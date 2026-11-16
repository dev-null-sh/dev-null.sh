<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProfileType;
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
        $handles = $this->mentions->handles("{$user->bio}\n{$user->company}");

        if ($handles === []) {
            return [];
        }

        return Profile::query()
            ->whereIn('handle', $handles)
            ->get(['handle'])
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => $profile->handle])
            ->all();
    }

    /**
     * What each of those profiles opens as, by lowercase handle, the way "/@{handle}" decides: a user's profile when a
     * user has the handle, an organization's page, or an unclaimed developer's page.
     *
     * @return array<string, 'user'|'organization'|'unclaimed'>
     */
    public function types(User $user): array
    {
        $handles = $this->mentions->handles("{$user->bio}\n{$user->company}");

        if ($handles === []) {
            return [];
        }

        return Profile::query()
            ->select(['id', 'handle', 'type'])
            ->whereIn('handle', $handles)
            ->withExists('user')
            ->get()
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => match (true) {
                (bool) $profile->getAttribute('user_exists') => 'user',
                $profile->type === ProfileType::Organization => 'organization',
                default => 'unclaimed',
            }])
            ->all();
    }
}
