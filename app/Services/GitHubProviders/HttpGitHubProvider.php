<?php

declare(strict_types=1);

namespace App\Services\GitHubProviders;

use App\Contracts\Services\GitHubProvider;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Account from GitHubProvider
 * @phpstan-import-type Commit from GitHubProvider
 * @phpstan-import-type SearchResults from GitHubProvider
 * @phpstan-import-type Issue from GitHubProvider
 * @phpstan-import-type RateLimit from GitHubProvider
 * @phpstan-import-type Sponsor from GitHubProvider
 */
final readonly class HttpGitHubProvider implements GitHubProvider
{
    private const int PerPage = 100;

    /**
     * @return RateLimit
     */
    public function rateLimit(): array
    {
        $core = $this->request()->get('rate_limit')->throw()->fluent();

        return [
            'remaining' => $core->integer('resources.core.remaining'),
            'limit' => $core->integer('resources.core.limit'),
            'resets_at' => CarbonImmutable::createFromTimestamp($core->integer('resources.core.reset')),
        ];
    }

    public function account(string $githubId): ?array
    {
        return $this->find("user/{$githubId}");
    }

    public function accountByLogin(string $login): ?array
    {
        return $this->find("users/{$login}");
    }

    public function login(string $githubId): ?string
    {
        $account = $this->account($githubId);

        return $account === null ? null : new Fluent($account)->string('login')->value();
    }

    public function isPublicMember(string $organizationGithubId, string $githubId): bool
    {
        $login = $this->login($githubId);

        if ($login === null) {
            return false;
        }

        $response = $this->request()->get("organizations/{$organizationGithubId}/public_members/{$login}");

        return ! $response->notFound() && $response->throw()->noContent();
    }

    public function publicOrganizationMemberIds(string $login): ?array
    {
        $ids = collect();

        for ($page = 1; $page <= 10; $page++) {
            $response = $this->request()->get('orgs/'.rawurlencode($login).'/public_members', ['per_page' => self::PerPage, 'page' => $page]);

            if ($response->notFound()) {
                return null;
            }

            $ids = $ids->concat($response->throw()->collect()->mapInto(Fluent::class)->map(fn (Fluent $member): string => $member->string('id')->value()));

            if (! str_contains($response->header('Link'), 'rel="next"')) {
                return array_values($ids->unique()->all());
            }
        }

        return null;
    }

    /**
     * The REST API doesn't say, so this asks GraphQL with the user's own token. Everything inside the listing, even
     * whether it's public, needs a read:user or read:org token, and sign-in only asks for user:email.
     */
    public function hasSponsorsListing(string $token): ?bool
    {
        try {
            $response = $this->request($token)->connectTimeout(2)->timeout(3)->post('graphql', [
                'query' => 'query { viewer { hasSponsorsListing } }',
            ]);
        } catch (ConnectionException $connectionException) {
            Log::warning('Could not check the GitHub Sponsors listing.', ['error' => $connectionException->getMessage()]);

            return null;
        }

        if ($response->failed() || $response->json('errors') !== null) {
            Log::warning('Could not check the GitHub Sponsors listing.', [
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? $response->json('message'),
            ]);

            return null;
        }

        return $response->json('data.viewer.hasSponsorsListing') === true;
    }

    /**
     * GitHub often gives no total, so it is counted from up to a thousand events of the history when GitHub has none: a
     * one-time tier once, and a monthly tier for each month it was held, started months included. A payment that failed or was refunded still
     * counts. An equal total goes to the bigger tier.
     *
     * @return list<Sponsor>
     */
    public function publicSponsors(string $organization): array
    {
        $token = config('services.github.sponsors_token');
        assert(is_string($token));

        $sponsorships = $this->graphql($token, <<<'GRAPHQL'
            query ($login: String!) {
              organization(login: $login) {
                sponsorshipsAsMaintainer(first: 100, activeOnly: false, includePrivate: false) {
                  nodes {
                    tier { monthlyPriceInCents }
                    sponsorEntity {
                      ... on User { login databaseId totalSponsorshipAmountAsSponsorInCents(sponsorableLogins: [$login]) }
                      ... on Organization { login databaseId totalSponsorshipAmountAsSponsorInCents(sponsorableLogins: [$login]) }
                    }
                  }
                }
              }
            }
            GRAPHQL, ['login' => $organization])->collect('organization.sponsorshipsAsMaintainer.nodes')->mapInto(Fluent::class);

        $activities = collect();
        $cursor = null;

        for ($page = 1; $page <= 10; $page++) {
            $connection = $this->graphql($token, <<<'GRAPHQL'
                query ($login: String!, $after: String) {
                  organization(login: $login) {
                    sponsorsActivities(first: 100, after: $after, period: ALL, orderBy: {field: TIMESTAMP, direction: ASC}) {
                      pageInfo { hasNextPage endCursor }
                      nodes {
                        action
                        timestamp
                        sponsor { ... on User { login } ... on Organization { login } }
                        sponsorsTier { monthlyPriceInCents isOneTime }
                      }
                    }
                  }
                }
                GRAPHQL, ['login' => $organization, 'after' => $cursor])->scope('organization.sponsorsActivities');

            $activities = $activities->concat($connection->collect('nodes')->mapInto(Fluent::class));

            if (! $connection->boolean('pageInfo.hasNextPage')) {
                break;
            }

            $cursor = $connection->string('pageInfo.endCursor')->value();
        }

        $paidFromHistory = $activities
            ->groupBy(fn (Fluent $activity): string => $activity->string('sponsor.login')->value())
            ->map(fn (Collection $sponsorActivities): int => $this->paidFromHistory(...$sponsorActivities->all()));

        return array_values($sponsorships
            ->filter(fn (Fluent $sponsorship): bool => $sponsorship->has('sponsorEntity.login'))
            ->sortByDesc(fn (Fluent $sponsorship): int => $sponsorship->integer('tier.monthlyPriceInCents'))
            ->unique(fn (Fluent $sponsorship): string => $sponsorship->string('sponsorEntity.login')->value())
            ->map(fn (Fluent $sponsorship): array => [
                'login' => $sponsorship->string('sponsorEntity.login')->value(),
                'github_id' => $sponsorship->string('sponsorEntity.databaseId')->value(),
                'amount_in_cents' => $sponsorship->integer('sponsorEntity.totalSponsorshipAmountAsSponsorInCents')
                    ?: $paidFromHistory->get($sponsorship->string('sponsorEntity.login')->value(), 0),
                'tier_in_cents' => $sponsorship->integer('tier.monthlyPriceInCents'),
            ])
            ->sortBy([
                fn (array $first, array $second): int => $second['amount_in_cents'] <=> $first['amount_in_cents'],
                fn (array $first, array $second): int => $second['tier_in_cents'] <=> $first['tier_in_cents'],
            ])
            ->map(fn (array $sponsor): array => [
                'login' => $sponsor['login'],
                'github_id' => $sponsor['github_id'],
                'amount_in_cents' => $sponsor['amount_in_cents'],
            ])
            ->all());
    }

    public function followingIds(string $login, int $maxPages): array
    {
        $accounts = $this->accounts("users/{$login}/following", $maxPages);
        $ids = fn (bool $organizations): array => array_values(array_map(
            fn (array $account): string => (string) $account['id'],
            array_filter($accounts, fn (array $account): bool => ($account['type'] === 'Organization') === $organizations),
        ));

        return ['users' => $ids(false), 'organizations' => $ids(true)];
    }

    public function followerIds(string $login, int $maxPages): array
    {
        return array_map(fn (array $account): string => (string) $account['id'], $this->accounts("users/{$login}/followers", $maxPages));
    }

    public function sshKeys(string $login): array
    {
        /** @var list<string> */
        return collect(["users/{$login}/keys", "users/{$login}/ssh_signing_keys"])
            ->flatMap(fn (string $path): array => $this->request()->get($path, ['per_page' => self::PerPage])->throw()->collect()->pluck('key')->all())
            ->values()
            ->all();
    }

    public function avatar(string $url): ?string
    {
        try {
            $response = Http::timeout(3)->connectTimeout(2)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    public function readme(string $login): ?string
    {
        $response = $this->request()
            ->replaceHeaders(['Accept' => 'application/vnd.github.html+json'])
            ->get("repos/{$login}/{$login}/readme");

        return $response->notFound() ? null : $response->throw()->body();
    }

    /**
     * @return list<Commit>
     */
    public function commits(string $repository, string $branch, int $page, int $perPage): array
    {
        return array_values($this->request()->get("repos/{$repository}/commits", [
            'sha' => $branch,
            'per_page' => $perPage,
            'page' => $page,
        ])->throw()->collect()->mapInto(Fluent::class)->map(fn (Fluent $commit): array => [
            'sha' => $commit->string('sha')->value(),
            'message' => $commit->string('commit.message')->value(),
            'url' => $commit->string('html_url')->value(),
            'committed_at' => $commit->string('commit.committer.date')->value(),
            'parents' => array_values($commit->collect('parents')->mapInto(Fluent::class)->map(fn (Fluent $parent): string => $parent->string('sha')->value())->all()),
            'author' => $commit->get('author') === null ? [
                'id' => null,
                'login' => $commit->string('commit.author.name')->value(),
                'avatar_url' => 'https://avatars.githubusercontent.com/u/10137',
            ] : [
                'id' => $commit->integer('author.id'),
                'login' => $commit->string('author.login')->value(),
                'avatar_url' => $commit->string('author.avatar_url')->value(),
            ],
        ])->all());
    }

    public function contributors(string $repository, int $page, int $perPage): array
    {
        /** @var list<array<string, mixed>> */
        return $this->request()->get("repos/{$repository}/contributors", [
            'per_page' => $perPage,
            'page' => $page,
        ])->throw()->json();
    }

    /**
     * @return Issue|null
     */
    public function issue(string $repository, int $number): ?array
    {
        $response = $this->request()->get("repos/{$repository}/issues/{$number}");

        if ($response->notFound()) {
            return null;
        }

        $issue = $response->throw()->fluent();

        return [
            'number' => $issue->integer('number'),
            'title' => $issue->string('title')->value(),
            'url' => $issue->string('html_url')->value(),
        ];
    }

    public function createIssue(string $title, string $body, array $labels): array
    {
        $repository = config('services.github.repository');
        assert(is_string($repository));

        $issue = $this->request($this->installationToken())
            ->post("repos/{$repository}/issues", [
                'title' => $title,
                'body' => $body,
                'labels' => $labels,
            ])
            ->throw()
            ->fluent();

        return ['number' => $issue->integer('number'), 'url' => $issue->string('html_url')->value()];
    }

    /**
     * @return SearchResults
     */
    public function searchIssues(string $query, int $perPage, ?string $sort): array
    {
        return $this->search('search/issues', [
            'q' => $query,
            'per_page' => $perPage,
            ...$sort === null ? [] : ['sort' => $sort, 'order' => 'desc'],
        ]);
    }

    /**
     * @return SearchResults
     */
    public function searchCommits(string $query, int $perPage): array
    {
        return $this->search('search/commits', ['q' => $query, 'per_page' => $perPage]);
    }

    public function repository(string $repository): array
    {
        $repository = $this->request()->get("repos/{$repository}")->throw()->fluent();

        return [
            'stars' => $repository->integer('stargazers_count'),
            'forks' => $repository->integer('forks_count'),
        ];
    }

    public function languages(string $repository): array
    {
        /** @var array<string, int> */
        return $this->request()->get("repos/{$repository}/languages")->throw()->json();
    }

    /**
     * @param  Fluent<array-key, mixed>  ...$activities
     */
    private function paidFromHistory(Fluent ...$activities): int
    {
        $paid = 0;
        $monthlyPrice = 0;
        $heldSince = null;

        foreach ($activities as $activity) {
            $action = $activity->string('action')->value();
            $at = CarbonImmutable::parse($activity->string('timestamp')->value());

            if ($heldSince instanceof CarbonImmutable && in_array($action, ['TIER_CHANGE', 'CANCELLED_SPONSORSHIP'], true)) {
                $paid += $monthlyPrice * $this->monthsStarted($heldSince, $at);
                $heldSince = null;
            }

            if (in_array($action, ['NEW_SPONSORSHIP', 'TIER_CHANGE'], true)) {
                if ($activity->boolean('sponsorsTier.isOneTime')) {
                    $paid += $activity->integer('sponsorsTier.monthlyPriceInCents');
                } else {
                    $monthlyPrice = $activity->integer('sponsorsTier.monthlyPriceInCents');
                    $heldSince = $at;
                }
            }
        }

        return $heldSince instanceof CarbonImmutable ? $paid + $monthlyPrice * $this->monthsStarted($heldSince, CarbonImmutable::now()) : $paid;
    }

    private function monthsStarted(CarbonImmutable $from, CarbonImmutable $until): int
    {
        return (int) floor($from->diffInMonths($until)) + 1;
    }

    /**
     * @param  array<string, string|null>  $variables
     * @return Fluent<string, mixed>
     */
    private function graphql(string $token, string $query, array $variables): Fluent
    {
        $response = $this->request($token)->post('graphql', ['query' => $query, 'variables' => $variables])->throw();

        throw_if($response->json('errors') !== null, RequestException::class, $response);

        return $response->fluent('data');
    }

    private function request(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(3)
            ->timeout(5);

        if ($token !== null) {
            return $request->withToken($token);
        }

        $clientId = config('services.github.client_id');
        assert(is_string($clientId));

        $clientSecret = config('services.github.client_secret');
        assert(is_string($clientSecret));

        // Authenticating as the OAuth app raises the rate limit from 60 to 5,000 requests per hour.
        return $request->withBasicAuth($clientId, $clientSecret);
    }

    /**
     * @return Account|null
     */
    private function find(string $path): ?array
    {
        $response = $this->request()->get($path);

        if ($response->notFound()) {
            return null;
        }

        /** @var Account */
        return $response->throw()->json();
    }

    /**
     * The accounts in a paginated list, up to the given number of pages.
     *
     * @return list<array{id: int, type: string}>
     */
    private function accounts(string $path, int $maxPages): array
    {
        $accounts = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            /** @var list<array{id: int, type: string}> $pageAccounts */
            $pageAccounts = $this->request()->get($path, ['per_page' => self::PerPage, 'page' => $page])->throw()->json();

            array_push($accounts, ...$pageAccounts);

            if (count($pageAccounts) < self::PerPage) {
                break;
            }
        }

        return $accounts;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return SearchResults
     */
    private function search(string $path, array $parameters): array
    {
        $results = $this->request()->get($path, $parameters)->throw()->fluent();

        /** @var list<array<string, mixed>> $items */
        $items = $results->array('items');

        return [
            'incomplete' => $results->boolean('incomplete_results'),
            'total' => $results->integer('total_count'),
            'items' => $items,
        ];
    }

    /**
     * The GitHub App installed on this repository only: its installation tokens are limited to writing issues there,
     * so no user ever grants access to their own repositories. A token lasts an hour, so it is reused for 50 minutes,
     * encrypted at rest in the cache.
     */
    private function installationToken(): string
    {
        $repository = config('services.github.repository');
        assert(is_string($repository));

        $token = Cache::get("github-app:{$repository}:installation-token");

        if (is_string($token)) {
            return Crypt::decryptString($token);
        }

        $jwt = $this->jwt();

        $installation = $this->request($jwt)
            ->get("repos/{$repository}/installation")
            ->throw()
            ->fluent()
            ->integer('id');

        $token = $this->request($jwt)
            ->post("app/installations/{$installation}/access_tokens", [
                'repositories' => [Str::after($repository, '/')],
                'permissions' => ['issues' => 'write'],
            ])
            ->throw()
            ->fluent()
            ->string('token')
            ->value();

        Cache::put("github-app:{$repository}:installation-token", Crypt::encryptString($token), now()->addMinutes(50));

        return $token;
    }

    /**
     * The app authenticates with a short-lived JWT, backdated a minute to allow for clock drift with GitHub.
     */
    private function jwt(): string
    {
        $appId = config('services.github_app.id');
        assert(is_string($appId));

        $privateKey = config('services.github_app.private_key');
        assert(is_string($privateKey));

        return JWT::encode([
            'iat' => now()->subMinute()->getTimestamp(),
            'exp' => now()->addMinutes(9)->getTimestamp(),
            'iss' => $appId,
        ], str_replace('\n', "\n", $privateKey), 'RS256');
    }
}
