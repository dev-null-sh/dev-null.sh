<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;
use RuntimeException;

/**
 * GitHub accounts that have commits on the repository's default branch. Cached; soft-fails to nobody when GitHub is down.
 */
final class RepositoryContributorsQuery
{
    private const string CacheKey = 'repository-contributors:usernames';

    private const int CacheSeconds = 900;

    private const int MaxPages = 5;

    public function __construct(private GitHub $github)
    {
        //
    }

    public function contains(string $username): bool
    {
        return in_array(mb_strtolower($username), $this->usernames(), true);
    }

    /**
     * @return list<string>
     */
    public function usernames(): array
    {
        return once(function (): array {
            /** @var list<string>|null $cached */
            $cached = Cache::get(self::CacheKey);

            if (is_array($cached)) {
                return $cached;
            }

            try {
                $usernames = $this->fetch();
                Cache::put(self::CacheKey, $usernames, self::CacheSeconds);

                return $usernames;
            } catch (HttpClientException|RuntimeException) {
                return [];
            }
        });
    }

    /**
     * @return list<string>
     */
    private function fetch(): array
    {
        $usernames = [];

        for ($page = 1; $page <= self::MaxPages; $page++) {
            $contributors = $this->github->request()->get('repos/'.ChangelogQuery::Repository.'/contributors', [
                'per_page' => 100,
                'page' => $page,
            ])->throw()->collect();

            foreach ($contributors as $contributor) {
                $contributor = new Fluent($contributor);

                if ($contributor->string('type')->value() !== 'User') {
                    continue;
                }

                $login = mb_strtolower($contributor->string('login')->value());

                if ($login !== '') {
                    $usernames[] = $login;
                }
            }

            if ($contributors->count() < 100) {
                break;
            }
        }

        return array_values(array_unique($usernames));
    }
}
