<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Carbon\CarbonImmutable;

/**
 * GitHub, which /dev/null is built around: every account, SSH key and organization comes from it, and so does this
 * repository's changelog and contributors. Every method throws an Illuminate\Http\Client\HttpClientException when
 * GitHub doesn't answer.
 *
 * @phpstan-type Account array<array-key, mixed>
 * @phpstan-type PullRequest array{number: int, title: string, html_url: string, merged_at: string|null, updated_at: string, merge_commit_sha: string, user: array{id: int, login: string, avatar_url: string}}
 * @phpstan-type SearchResults array{incomplete: bool, total: int, items: list<array<string, mixed>>}
 * @phpstan-type Issue array{number: int, title: string, url: string}
 * @phpstan-type RateLimit array{remaining: int, limit: int, resets_at: CarbonImmutable}
 */
interface GitHubProvider
{
    /**
     * The requests left out of the hourly budget the calls without a user's token draw from. Reading it costs none.
     *
     * @return RateLimit|null
     */
    public function rateLimit(): ?array;

    /**
     * Looks the account up by its immutable ID: a handle can be renamed and then claimed by someone else. Null when the
     * account no longer exists.
     *
     * @return Account|null
     */
    public function account(string $githubId): ?array;

    /**
     * @return Account|null
     */
    public function accountByLogin(string $login): ?array;

    /**
     * The handle of the account, looked up by its immutable ID. Null when the account no longer exists.
     */
    public function login(string $githubId): ?string;

    /**
     * Whether the user shows their membership of the organization on GitHub, which only members can do. Both are
     * looked up by their immutable ID, so a renamed handle that someone else took since doesn't count.
     */
    public function isPublicMember(string $organizationGithubId, string $githubId): bool;

    /**
     * Whether the user the token is for has a GitHub Sponsors listing. Null when GitHub can't say.
     */
    public function hasSponsorsListing(string $token): ?bool;

    /**
     * The IDs of the accounts the user follows, up to the given number of pages.
     *
     * @return list<string>
     */
    public function followingIds(string $login, int $maxPages): array;

    /**
     * The IDs of the accounts following the user, up to the given number of pages.
     *
     * @return list<string>
     */
    public function followerIds(string $login, int $maxPages): array;

    /**
     * The public SSH keys of the account, for authentication and signing, in OpenSSH format.
     *
     * @return list<string>
     */
    public function sshKeys(string $login): array;

    /**
     * The image behind an account's avatar URL, null when it can't be fetched.
     */
    public function avatar(string $url): ?string;

    /**
     * The README of the account's profile repository, rendered as HTML by GitHub. Null when there is none.
     */
    public function readme(string $login): ?string;

    /**
     * One page of the closed pull requests into the branch, most recently updated first.
     *
     * @return list<PullRequest>
     */
    public function closedPullRequests(string $repository, string $branch, int $page, int $perPage): array;

    /**
     * One page of the repository's contributors, most commits first, as GitHub lists them.
     *
     * @return list<array<string, mixed>>
     */
    public function contributors(string $repository, int $page, int $perPage): array;

    /**
     * The issue or pull request with the number: they share a single sequence. Null when there is none.
     *
     * @return Issue|null
     */
    public function issue(string $repository, int $number): ?array;

    /**
     * Creates an issue on this repository, as the GitHub App installed on it.
     *
     * @param  list<string>  $labels
     * @return array{number: int, url: string}
     */
    public function createIssue(string $title, string $body, array $labels): array;

    /**
     * @return SearchResults
     */
    public function searchIssues(string $query, int $perPage, ?string $sort): array;

    /**
     * @return SearchResults
     */
    public function searchCommits(string $query, int $perPage): array;

    /**
     * @return array{stars: int, forks: int}
     */
    public function repository(string $repository): array;

    /**
     * The bytes of code in each language of the repository.
     *
     * @return array<string, int>
     */
    public function languages(string $repository): array;
}
