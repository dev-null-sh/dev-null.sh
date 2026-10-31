<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type Account array{login: string, github_id: string, commits: int}
 * @phpstan-type Contributor array{login: string, commits: int, handle: string|null}
 */
final readonly class ContributorsQuery
{
    private const int PerPage = 100;

    private const int MaxPages = 10;

    private const int Ttl = 3600;

    private const int FailedTtl = 60;

    private const int LastTtl = 604800;

    /**
     * GitHub reports them as ordinary accounts, so they are named rather than detected.
     */
    private const array Agents = ['ampagent', 'claude'];

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * The repository contributors, most commits first, as GitHub counts them, each with their /dev/null handle, or
     * null when they aren't on it. They are found by their GitHub account, since a handle can change on either side.
     *
     * @return list<Contributor>|null
     */
    public function get(): ?array
    {
        $accounts = $this->accounts();

        if ($accounts === null) {
            return null;
        }

        $handles = User::query()
            ->whereIn('github_id', array_column($accounts, 'github_id'))
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->github_id => $user->handle]);

        return array_map(fn (array $account): array => [
            'login' => $account['login'],
            'commits' => $account['commits'],
            'handle' => $handles->get($account['github_id']),
        ], $accounts);
    }

    /**
     * Whether the user is a contributor, found by their GitHub account. False while GitHub can't say and there is no
     * recent list to fall back on.
     */
    public function includes(User $user): bool
    {
        return in_array($user->github_id, array_column($this->accounts() ?? [], 'github_id'), true);
    }

    /**
     * The GitHub accounts behind the contributors. Once there is a list from the past week, nobody waits for GitHub: an
     * expired list is served for another minute while GitHub is asked after the response, so other visitors don't ask
     * meanwhile. Without one, GitHub is asked right away, and null means it didn't answer.
     *
     * @return list<Account>|null
     */
    private function accounts(): ?array
    {
        $repository = config()->string('services.github.repository');
        $key = "about:contributor-accounts:{$repository}";

        /** @var list<Account>|false|null $contributors */
        $contributors = Cache::get($key);

        if ($contributors === null) {
            /** @var list<Account>|null $contributors */
            $contributors = Cache::get("{$key}:last");

            if ($contributors === null) {
                $contributors = $this->refresh($repository, $key);
            } else {
                Cache::put($key, $contributors, self::FailedTtl);
                defer(fn (): array|false => $this->refresh($repository, $key));
            }
        }

        return $contributors === false ? null : $contributors;
    }

    /**
     * Asks GitHub for the list and caches it for an hour, keeping it for a week to fall back on. When GitHub doesn't
     * answer, that fallback, or false when there is none, is cached for a minute instead.
     *
     * @return list<Account>|false
     */
    private function refresh(string $repository, string $key): array|false
    {
        $contributors = [];

        try {
            for ($page = 1; $page <= self::MaxPages; $page++) {
                $accounts = collect($this->github->contributors($repository, $page, self::PerPage));

                $contributors = [...$contributors, ...$accounts->where('type', 'User')
                    ->mapInto(Fluent::class)
                    ->filter(fn (Fluent $account): bool => $account->string('login')->isNotEmpty()
                        && is_int($account->get('id'))
                        && is_int($account->get('contributions'))
                        && ! in_array($account->string('login')->lower()->value(), self::Agents, true))
                    ->map(fn (Fluent $account): array => [
                        'login' => $account->string('login')->value(),
                        'github_id' => (string) $account->integer('id'),
                        'commits' => $account->integer('contributions'),
                    ])
                    ->values()
                    ->all()];

                if ($accounts->count() < self::PerPage) {
                    break;
                }
            }
        } catch (HttpClientException) {
            /** @var list<Account>|false $contributors */
            $contributors = Cache::get("{$key}:last", false);
            Cache::put($key, $contributors, self::FailedTtl);

            return $contributors;
        }

        Cache::put("{$key}:last", $contributors, self::LastTtl);
        Cache::put($key, $contributors, self::Ttl);

        return $contributors;
    }
}
