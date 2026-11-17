<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Profile;
use App\Services\Mentions;
use Illuminate\Support\Facades\Cache;

final readonly class UnclaimedProfileMentionCountQuery
{
    /**
     * Mentions only go up a little at a time, and counting them reads through the commits, so the count is kept an hour.
     */
    private const int CACHE_SECONDS = 3600;

    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * How many approved commits mention the developer as "@handle", by the same rules that link mentions, so one in
     * code doesn't count. Just a number on their page: what others say about someone who isn't here stays off it.
     * A handle has only letters, digits and "-", so it can't widen the pattern.
     */
    public function get(Profile $profile): int
    {
        $handle = mb_strtolower($profile->handle);

        return (int) Cache::remember("unclaimed-profile-mentions:{$handle}", self::CACHE_SECONDS, fn (): int => Commit::query()
            ->approved()
            ->where('body', 'ilike', "%@{$handle}%")
            ->select(['id', 'body'])
            ->lazyById(500)
            ->filter(fn (Commit $commit): bool => in_array($handle, $this->mentions->handles($commit->body), true))
            ->count());
    }
}
