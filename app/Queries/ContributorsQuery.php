<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
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

    /**
     * GitHub reports them as ordinary accounts, so they are named rather than detected.
     */
    private const array Agents = ['ampagent', 'claude'];

    public function __construct(private GitHub $github)
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
     * How many commits the user's GitHub account has in the repository, as GitHub counted them when the list was last
     * read. It only reads the cache, which the scheduler keeps warm, so a profile or a commit never waits on GitHub.
     * Zero when the account has none, or the list hasn't been read.
     */
    public function commits(User $user): int
    {
        /** @var list<Account>|false|null $accounts */
        $accounts = Cache::get($this->key());

        return array_column(is_array($accounts) ? $accounts : [], 'commits', 'github_id')[$user->github_id] ?? 0;
    }

    /**
     * Reads the list from GitHub again before the cached one expires. A failed read keeps the list there is.
     */
    public function refresh(): void
    {
        $accounts = $this->fetch();

        if ($accounts !== null) {
            Cache::put($this->key(), $accounts, self::Ttl);
        }
    }

    /**
     * The GitHub accounts behind the contributors. A failed read is kept for a minute, so an outage doesn't send every
     * visitor to GitHub.
     *
     * @return list<Account>|null
     */
    private function accounts(): ?array
    {
        /** @var list<Account>|false|null $contributors */
        $contributors = Cache::get($this->key());

        if ($contributors === null) {
            $contributors = $this->fetch() ?? false;

            Cache::put($this->key(), $contributors, $contributors === false ? self::FailedTtl : self::Ttl);
        }

        return $contributors === false ? null : $contributors;
    }

    /**
     * @return list<Account>|null
     */
    private function fetch(): ?array
    {
        $repository = config()->string('services.github.repository');
        $contributors = [];

        try {
            for ($page = 1; $page <= self::MaxPages; $page++) {
                $accounts = $this->github->request()->get("repos/{$repository}/contributors", [
                    'per_page' => self::PerPage,
                    'page' => $page,
                ])->throw()->collect();

                if ($accounts->isNotEmpty() && ! array_is_list($accounts->all())) {
                    return null;
                }

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
            return null;
        }

        return $contributors;
    }

    private function key(): string
    {
        return 'about:contributor-accounts:'.config()->string('services.github.repository');
    }
}
