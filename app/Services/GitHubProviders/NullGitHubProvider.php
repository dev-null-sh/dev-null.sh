<?php

declare(strict_types=1);

namespace App\Services\GitHubProviders;

use App\Contracts\Services\GitHubProvider;
use App\Enums\GitHubOrganizationMembership;

/**
 * A GitHub that knows nobody and holds nothing, so developing and testing never reach GitHub or spend its rate limit.
 */
final readonly class NullGitHubProvider implements GitHubProvider
{
    public function rateLimit(): ?array
    {
        return null;
    }

    public function account(string $githubId): ?array
    {
        return null;
    }

    public function accountByLogin(string $login): ?array
    {
        return null;
    }

    public function login(string $githubId): ?string
    {
        return null;
    }

    public function organizationMembership(string $token, string $handle, string $githubId): GitHubOrganizationMembership
    {
        return GitHubOrganizationMembership::NotMember;
    }

    public function hasSponsorsListing(string $token): ?bool
    {
        return null;
    }

    public function followingIds(string $login, int $maxPages): array
    {
        return [];
    }

    public function followerIds(string $login, int $maxPages): array
    {
        return [];
    }

    public function sshKeys(string $login): array
    {
        return [];
    }

    public function avatar(string $url): ?string
    {
        return null;
    }

    public function readme(string $login): ?string
    {
        return null;
    }

    public function closedPullRequests(string $repository, string $branch, int $page, int $perPage): array
    {
        return [];
    }

    public function contributors(string $repository, int $page, int $perPage): array
    {
        return [];
    }

    public function issue(string $repository, int $number): ?array
    {
        return null;
    }

    public function createIssue(string $title, string $body, array $labels): array
    {
        $repository = config('services.github.repository');
        assert(is_string($repository));

        return ['number' => 0, 'url' => "https://github.com/{$repository}/issues"];
    }

    public function searchIssues(string $query, int $perPage, ?string $sort): array
    {
        return ['incomplete' => false, 'total' => 0, 'items' => []];
    }

    public function searchCommits(string $query, int $perPage): array
    {
        return ['incomplete' => false, 'total' => 0, 'items' => []];
    }

    public function repository(string $repository): array
    {
        return ['stars' => 0, 'forks' => 0];
    }

    public function languages(string $repository): array
    {
        return [];
    }
}
