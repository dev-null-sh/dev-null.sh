<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

final readonly class RepositoryContributorsQuery
{
    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * GitHub counts contributions on the default branch (production). Cache across all visitors for one hour.
     *
     * @return Collection<int, array{login: string, commits: int}>
     */
    public function get(): Collection
    {
        $repository = config()->string('services.github.repository');

        return Cache::remember("repository-contributors:{$repository}", 3600, function () use ($repository): Collection {
            $contributors = collect();
            try {
                for ($page = 1; ; $page++) {
                    $response = $this->github->request()->get("repos/{$repository}/contributors", ['per_page' => 100, 'page' => $page])->throw();
                    $accounts = $response->collect();
                    $contributors = $contributors->concat($accounts->where('type', 'User')->mapInto(Fluent::class)->map(fn (Fluent $account): array => [
                        'login' => $account->string('login')->value(),
                        'commits' => $account->integer('contributions'),
                    ]));

                    if ($accounts->count() < 100) {
                        break;
                    }
                }
            } catch (HttpClientException) {
                return collect();
            }

            return $contributors->sortByDesc('commits')->values();
        });
    }
}
