<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Enums\GitHubOrganizationMembership;
use App\Services\GitHubProviders\NullGitHubProvider;

it('is the github provider in testing', function (): void {
    expect(resolve(GitHubProvider::class))->toBeInstanceOf(NullGitHubProvider::class);
});

it('knows nobody and holds nothing', function (): void {
    $provider = new NullGitHubProvider();
    $noResults = ['incomplete' => false, 'total' => 0, 'items' => []];

    expect($provider->rateLimit())->toBeNull()
        ->and($provider->account('583231'))->toBeNull()
        ->and($provider->accountByLogin('octocat'))->toBeNull()
        ->and($provider->login('583231'))->toBeNull()
        ->and($provider->organizationMembership('token', 'brefphp', '45861341'))->toBe(GitHubOrganizationMembership::NotMember)
        ->and($provider->hasSponsorsListing('token'))->toBeNull()
        ->and($provider->followingIds('octocat', 10))->toBe([])
        ->and($provider->followerIds('octocat', 10))->toBe([])
        ->and($provider->sshKeys('octocat'))->toBe([])
        ->and($provider->avatar('https://avatars.githubusercontent.com/u/583231'))->toBeNull()
        ->and($provider->readme('octocat'))->toBeNull()
        ->and($provider->closedPullRequests('dev-null-sh/dev-null.sh', 'production', 1, 100))->toBe([])
        ->and($provider->contributors('dev-null-sh/dev-null.sh', 1, 100))->toBe([])
        ->and($provider->issue('dev-null-sh/dev-null.sh', 1))->toBeNull()
        ->and($provider->createIssue('Broken', 'Broken.', ['bug']))->toBe(['number' => 0, 'url' => 'https://github.com/dev-null-sh/dev-null.sh/issues'])
        ->and($provider->searchIssues('repo:dev-null-sh/dev-null.sh', 10, 'updated'))->toBe($noResults)
        ->and($provider->searchCommits('repo:dev-null-sh/dev-null.sh', 5))->toBe($noResults)
        ->and($provider->repository('dev-null-sh/dev-null.sh'))->toBe(['stars' => 0, 'forks' => 0])
        ->and($provider->languages('dev-null-sh/dev-null.sh'))->toBe([]);
});
