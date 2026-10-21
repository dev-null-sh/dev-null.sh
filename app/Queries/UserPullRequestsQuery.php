<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class UserPullRequestsQuery
{
    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * @return array{count: int, url: string}|null
     */
    public function get(User $user): ?array
    {
        if (in_array($user->github_id, User::BOT_GITHUB_IDS, true)) {
            return null;
        }

        /** @var array{count: int, url: string}|false $result */
        $result = Cache::remember('github-pull-requests:'.$user->github_id, 900, function () use ($user): array|false {
            if (! ctype_digit($user->github_id)) {
                return false;
            }

            try {
                $login = $this->github->login($user->github_id);

                if ($login === null) {
                    return false;
                }

                $query = 'is:public is:pr is:merged author:'.$login;
                $response = $this->github->request()->get('search/issues', ['q' => $query, 'per_page' => 1])->throw();
                $count = $response->json('total_count');

                if ($response->json('incomplete_results') !== false || ! is_int($count) || $count < 0) {
                    return false;
                }

                return ['count' => $count, 'url' => 'https://github.com/search?type=pullrequests&q='.rawurlencode($query)];
            } catch (HttpClientException) {
                return false;
            }
        });

        return $result === false ? null : $result;
    }
}
