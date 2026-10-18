<?php

declare(strict_types=1);

use App\Dto\GitHubSponsorship;
use Carbon\CarbonImmutable;

it('maps a sponsorship node, dated by the later of its start and its tier', function (): void {
    $sponsorship = GitHubSponsorship::fromNode([
        'createdAt' => '2026-01-01T10:00:00Z',
        'tierSelectedAt' => '2026-06-01T10:00:00Z',
        'isActive' => true,
        'isOneTimePayment' => false,
        'account' => ['databaseId' => 583231],
    ]);

    expect($sponsorship)
        ->githubId->toBe('583231')
        ->isOneTime->toBeFalse()
        ->isActive->toBeTrue()
        ->and($sponsorship?->sponsoredAt->toIso8601String())->toBe('2026-06-01T10:00:00+00:00');
});

it('skips organizations and nodes it cannot read', function (array $node): void {
    expect(GitHubSponsorship::fromNode($node))->toBeNull();
})->with([
    'an organization' => [['createdAt' => '2026-01-01T10:00:00Z', 'account' => []]],
    'no date' => [['account' => ['databaseId' => 583231]]],
]);

it('lapses one-time sponsorships thirty days after the payment, and monthly ones once they end', function (): void {
    $paid = CarbonImmutable::parse('2026-10-01 12:00:00');
    $seen = CarbonImmutable::parse('2026-10-10 12:00:00');

    expect(new GitHubSponsorship('1', isOneTime: true, isActive: false, sponsoredAt: $paid)->lapsesAt(null)?->toDateTimeString())->toBe('2026-10-31 12:00:00')
        ->and(new GitHubSponsorship('1', isOneTime: false, isActive: true, sponsoredAt: $paid)->lapsesAt($seen))->toBeNull()
        ->and(new GitHubSponsorship('1', isOneTime: false, isActive: false, sponsoredAt: $paid)->lapsesAt($seen)?->toDateTimeString())->toBe('2026-10-10 12:00:00')
        ->and(new GitHubSponsorship('1', isOneTime: false, isActive: false, sponsoredAt: $paid)->lapsesAt(null)?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('keeps the active sponsorship of each account, or else the latest', function (): void {
    $past = new GitHubSponsorship('1', isOneTime: false, isActive: false, sponsoredAt: now()->subYear()->toImmutable());
    $current = new GitHubSponsorship('1', isOneTime: false, isActive: true, sponsoredAt: now()->subYears(2)->toImmutable());
    $older = new GitHubSponsorship('2', isOneTime: true, isActive: false, sponsoredAt: now()->subYear()->toImmutable());
    $newer = new GitHubSponsorship('2', isOneTime: true, isActive: false, sponsoredAt: now()->subMonths(6)->toImmutable());

    expect(GitHubSponsorship::latestByAccount([$past, $current, $newer, $older]))->toBe(['1' => $current, '2' => $newer]);
});
