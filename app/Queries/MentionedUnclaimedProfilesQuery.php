<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProfileType;
use App\Models\Profile;
use App\Services\Mentions;
use Illuminate\Support\Str;

final readonly class MentionedUnclaimedProfilesQuery
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * The developers mentioned in the text who aren't on /dev/null yet but have a page, by lowercase handle. A handle
     * that is a user's mentions the user, so it is left out.
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

        return Profile::query()
            ->where('type', ProfileType::User)
            ->whereIn('handle', $handles)
            ->whereDoesntHave('user')
            ->get()
            ->mapWithKeys(fn (Profile $profile): array => [Str::lower($profile->handle) => $profile->id])
            ->all();
    }
}
