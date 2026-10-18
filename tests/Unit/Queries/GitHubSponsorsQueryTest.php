<?php

declare(strict_types=1);

use App\Dto\GitHubSponsorship;
use App\Queries\GitHubSponsorsQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('reads both sides with the user token, leaving organizations out', function (): void {
    fakeGitHubSponsors(sponsoring: [sponsorshipNode(1)], sponsors: [sponsorshipNode(2, isOneTime: true), ['createdAt' => now()->toIso8601String(), 'account' => []]]);

    $sponsors = resolve(GitHubSponsorsQuery::class)->get('gho_octocat', 'octocat', withSponsors: true);

    expect($sponsors?->hasSponsorsListing)->toBeTrue()
        ->and(array_map(fn (GitHubSponsorship $sponsorship): string => $sponsorship->githubId, $sponsors->sponsoring ?? []))->toBe(['1'])
        ->and(array_map(fn (GitHubSponsorship $sponsorship): bool => $sponsorship->isOneTime, $sponsors->sponsors ?? []))->toBe([true]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer gho_octocat')
        && $request['variables'] === ['sponsoring' => true, 'sponsoringAfter' => null, 'sponsors' => true, 'sponsorsAfter' => null]);
});

it('only asks for the user own sponsors when wanted', function (): void {
    fakeGitHubSponsorsListing();

    $sponsors = resolve(GitHubSponsorsQuery::class)->get('gho_octocat', 'octocat', withSponsors: false);

    expect($sponsors?->sponsoring)->toBe([])
        ->and($sponsors?->sponsors)->toBeNull();
});

it('reads every page', function (): void {
    $page = fn (bool $hasNextPage, int $account): array => ['data' => ['viewer' => [
        'hasSponsorsListing' => true,
        'sponsors' => ['pageInfo' => ['hasNextPage' => $hasNextPage, 'endCursor' => $hasNextPage ? 'next' : null], 'nodes' => [sponsorshipNode($account)]],
    ]]];

    Http::fakeSequence('api.github.com/graphql')
        ->push(['data' => ['viewer' => [
            'hasSponsorsListing' => true,
            'sponsoring' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
            ...$page(true, 1)['data']['viewer'],
        ]]])
        ->push($page(false, 2));

    $sponsors = resolve(GitHubSponsorsQuery::class)->get('gho_octocat', 'octocat', withSponsors: true);

    expect(array_map(fn (GitHubSponsorship $sponsorship): string => $sponsorship->githubId, $sponsors->sponsors ?? []))->toBe(['1', '2']);

    Http::assertSent(fn (Request $request): bool => $request['variables'] === ['sponsoring' => false, 'sponsoringAfter' => null, 'sponsors' => true, 'sponsorsAfter' => 'next']);
});

it('leaves out a side it could not read to the end', function (): void {
    Http::fake(['api.github.com/graphql' => Http::response(['data' => ['viewer' => [
        'hasSponsorsListing' => true,
        'sponsoring' => ['pageInfo' => ['hasNextPage' => true, 'endCursor' => 'next'], 'nodes' => [sponsorshipNode(1)]],
        'sponsors' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => 'not a list'],
    ]]])]);

    $sponsors = resolve(GitHubSponsorsQuery::class)->get('gho_octocat', 'octocat', withSponsors: true);

    expect($sponsors?->sponsoring)->toBeNull()
        ->and($sponsors?->sponsors)->toBe([]);
    Http::assertSentCount(10);
});
