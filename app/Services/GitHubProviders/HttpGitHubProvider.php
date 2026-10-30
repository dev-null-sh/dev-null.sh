<?php

declare(strict_types=1);

namespace App\Services\GitHubProviders;

use App\Contracts\Services\GitHubProvider;
use App\Enums\GitHubOrganizationMembership;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Account from GitHubProvider
 * @phpstan-import-type PullRequest from GitHubProvider
 * @phpstan-import-type SearchResults from GitHubProvider
 * @phpstan-import-type Issue from GitHubProvider
 * @phpstan-import-type RateLimit from GitHubProvider
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

    public function organizationMembership(string $token, string $handle, string $githubId): GitHubOrganizationMembership
    {
        $response = $this->request($token)->get("user/memberships/orgs/{$handle}");

        if ($response->notFound()) {
            return GitHubOrganizationMembership::NotMember;
        }

        if ($response->forbidden() && $response->hasHeader('X-GitHub-SSO')) {
            return GitHubOrganizationMembership::SingleSignOn;
        }

        if ($response->forbidden() && str_contains($response->fluent()->string('message')->value(), 'OAuth App access restrictions')) {
            return GitHubOrganizationMembership::Restricted;
        }

        $membership = $response->throw()->fluent();

        return $membership->string('state')->value() === 'active'
            && in_array($membership->string('role')->value(), ['admin', 'member'], true)
            && (string) $membership->integer('organization.id') === $githubId
            ? GitHubOrganizationMembership::Member
            : GitHubOrganizationMembership::NotMember;
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

    public function followingIds(string $login, int $maxPages): array
    {
        return $this->accountIds("users/{$login}/following", $maxPages);
    }

    public function followerIds(string $login, int $maxPages): array
    {
        return $this->accountIds("users/{$login}/followers", $maxPages);
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
     * @return list<PullRequest>
     */
    public function closedPullRequests(string $repository, string $branch, int $page, int $perPage): array
    {
        /** @var list<PullRequest> */
        return $this->request()->get("repos/{$repository}/pulls", [
            'state' => 'closed',
            'base' => $branch,
            'sort' => 'updated',
            'direction' => 'desc',
            'per_page' => $perPage,
            'page' => $page,
        ])->throw()->json();
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
     * The IDs of the accounts in a paginated list, up to the given number of pages.
     *
     * @return list<string>
     */
    private function accountIds(string $path, int $maxPages): array
    {
        $ids = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            /** @var list<array{id: int}> $accounts */
            $accounts = $this->request()->get($path, ['per_page' => self::PerPage, 'page' => $page])->throw()->json();

            foreach ($accounts as $account) {
                $ids[] = (string) $account['id'];
            }

            if (count($accounts) < self::PerPage) {
                break;
            }
        }

        return $ids;
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
