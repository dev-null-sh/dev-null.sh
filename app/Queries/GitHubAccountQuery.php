<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dto\GitHubAccount;
use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class GitHubAccountQuery
{
    private const string Unavailable = 'unavailable';

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * The personal GitHub account behind a username, cached for a day. Null when GitHub has no such account, or it
     * belongs to an organization, which nobody can sign in as; that is cached too, so a mention of "@everyone" doesn't
     * ask GitHub on every visit. Null as well while GitHub can't be reached or rate limits us, which is cached for a
     * minute only, so a profile comes back soon without every visit waiting on GitHub in the meantime.
     */
    public function get(string $username): ?GitHubAccount
    {
        $key = 'github:account:'.Str::lower($username);

        /** @var array<array-key, mixed>|false|string|null $account */
        $account = Cache::get($key);

        if ($account === null) {
            $account = $this->fetch($username);

            Cache::put($key, $account, $account === self::Unavailable ? 60 : 86400);
        }

        return is_array($account) ? GitHubAccount::fromAccount($account) : null;
    }

    /**
     * Only what the profile shows is kept, not the whole account GitHub answers with.
     *
     * @return array<array-key, mixed>|false|string
     */
    private function fetch(string $username): array|false|string
    {
        try {
            $response = $this->github->request()->get('users/'.rawurlencode($username));

            if ($response->notFound()) {
                return false;
            }

            $account = $response->throw()->json();
        } catch (HttpClientException) {
            return self::Unavailable;
        }

        if (! is_array($account) || ! is_int($account['id'] ?? null) || ! is_string($account['login'] ?? null) || ($account['type'] ?? null) !== 'User') {
            return false;
        }

        return Arr::only($account, ['id', 'login', 'name', 'avatar_url']);
    }
}
