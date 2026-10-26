<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type Contributor array{login: string, commits: int}
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
     * The repository contributors, most commits first, as GitHub counts them. A failed read is kept for a
     * minute, so an outage doesn't send every visitor to GitHub.
     *
     * @return list<Contributor>|null
     */
    public function get(): ?array
    {
        $repository = config()->string('services.github.repository');
        $key = "about:contributors:{$repository}";

        /** @var list<Contributor>|false|null $contributors */
        $contributors = Cache::get($key);

        if ($contributors === null) {
            $contributors = [];

            try {
                for ($page = 1; $page <= self::MaxPages; $page++) {
                    $accounts = $this->github->request()->get("repos/{$repository}/contributors", [
                        'per_page' => self::PerPage,
                        'page' => $page,
                    ])->throw()->collect();

                    if ($accounts->isNotEmpty() && ! array_is_list($accounts->all())) {
                        $contributors = false;

                        break;
                    }

                    $contributors = [...$contributors, ...$accounts->where('type', 'User')
                        ->mapInto(Fluent::class)
                        ->filter(fn (Fluent $account): bool => $account->string('login')->isNotEmpty()
                            && is_int($account->get('contributions'))
                            && ! in_array($account->string('login')->lower()->value(), self::Agents, true))
                        ->map(fn (Fluent $account): array => [
                            'login' => $account->string('login')->value(),
                            'commits' => $account->integer('contributions'),
                        ])
                        ->values()
                        ->all()];

                    if ($accounts->count() < self::PerPage) {
                        break;
                    }
                }
            } catch (HttpClientException) {
                $contributors = false;
            }

            Cache::put($key, $contributors, $contributors === false ? self::FailedTtl : self::Ttl);
        }

        return $contributors === false ? null : $contributors;
    }
}
