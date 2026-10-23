<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Who has a commit on /dev/null's production branch, which is where every merged pull request lands. GitHub counts it
 * under the author's account, matched by its immutable ID so a renamed username keeps the badge.
 */
final readonly class UpstreamContributorsQuery
{
    private const string Key = 'upstream-contributors';

    private const int MaxPages = 10;

    public function __construct(private GitHub $github)
    {
        //
    }

    public function includes(User $user): bool
    {
        return in_array($user->github_id, $this->ids(), true);
    }

    /**
     * Cached for an hour. When GitHub doesn't answer, the last list it gave is used, and asked for again a minute later.
     * Only one request refreshes the list at a time: the others keep the last list meanwhile instead of all asking
     * GitHub at once.
     *
     * @return list<string>
     */
    private function ids(): array
    {
        /** @var list<string>|null $ids */
        $ids = Cache::get(self::Key);

        if ($ids !== null) {
            return $ids;
        }

        /** @var list<string>|false $refreshed */
        $refreshed = Cache::lock(self::Key.':refresh', 30)->get($this->refresh(...));

        if ($refreshed !== false) {
            return $refreshed;
        }

        /** @var list<string> $last */
        $last = Cache::get(self::Key.':last', []);

        return $last;
    }

    /**
     * Another request may have refreshed the list while this one waited for its turn, so the cache is read again first.
     *
     * @return list<string>
     */
    private function refresh(): array
    {
        /** @var list<string>|null $ids */
        $ids = Cache::get(self::Key);

        if ($ids !== null) {
            return $ids;
        }

        try {
            $ids = $this->github->accountIds('repos/'.config()->string('services.github.repository').'/contributors', self::MaxPages);
            Cache::put(self::Key.':last', $ids);
            Cache::put(self::Key, $ids, now()->addHour());
        } catch (Throwable) {
            /** @var list<string> $ids */
            $ids = Cache::get(self::Key.':last', []);
            Cache::put(self::Key, $ids, now()->addMinute());
        }

        return $ids;
    }
}
