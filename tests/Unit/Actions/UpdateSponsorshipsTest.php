<?php

declare(strict_types=1);

use App\Actions\UpdateSponsorships;
use App\Dto\GitHubSponsors;
use App\Dto\GitHubSponsorship;
use App\Enums\OrgRole;
use App\Enums\SponsorsFrom;
use App\Models\Sponsorship;
use App\Models\User;
use Carbon\CarbonImmutable;

function githubSponsorship(User $account, bool $isOneTime = false, bool $isActive = true, ?CarbonImmutable $sponsoredAt = null): GitHubSponsorship
{
    return new GitHubSponsorship($account->github_id, $isOneTime, $isActive, $sponsoredAt ?? now()->subMonths(3)->toImmutable());
}

it('lets a sponsor into the orgs of the maintainers they sponsor, and only theirs', function (): void {
    [$taylor, $nuno, $mark] = User::factory()->count(3)->create();
    $taylorsOrg = createSponsorsOrg($taylor);
    $sponsor = User::factory()->create();

    resolve(UpdateSponsorships::class)->handle($sponsor, new GitHubSponsors(true, sponsoring: [
        githubSponsorship($taylor),
        githubSponsorship($nuno),
    ], sponsors: null));

    expect($taylorsOrg->membershipOf($sponsor))
        ->role->toBe(OrgRole::Contributor)
        ->is_hidden->toBeTrue()
        ->and(Sponsorship::query()->pluck('sponsorable_id')->all())->toBe([$taylor->id]);

    resolve(UpdateSponsorships::class)->handle($sponsor, new GitHubSponsors(true, sponsoring: [githubSponsorship($mark)], sponsors: null));

    expect($taylorsOrg->membershipOf($sponsor))->toBeNull()
        ->and(Sponsorship::query()->count())->toBe(0);
});

it('mirrors the maintainer own sponsors, only those on dev/null', function (): void {
    $maintainer = User::factory()->create();
    $org = createSponsorsOrg($maintainer);
    [$monthly, $once, $gone] = User::factory()->count(3)->create();
    Sponsorship::factory()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $gone->id]);

    resolve(UpdateSponsorships::class)->handle($maintainer, new GitHubSponsors(true, sponsoring: null, sponsors: [
        githubSponsorship($monthly),
        githubSponsorship($once, isOneTime: true, sponsoredAt: now()->subDays(10)->toImmutable()),
        githubSponsorship($maintainer),
        new GitHubSponsorship('999999999999', false, true, now()->toImmutable()),
    ]));

    $sponsorships = $maintainer->sponsorshipsAsMaintainer()->get()->keyBy('sponsor_id');

    expect($sponsorships->keys()->sort()->values()->all())->toBe(collect([$monthly->id, $once->id])->sort()->values()->all())
        ->and($sponsorships[$monthly->id]->lapses_at)->toBeNull()
        ->and($sponsorships[$once->id]->lapses_at?->toDateString())->toBe(now()->addDays(20)->toDateString())
        ->and($org->participants()->count())->toBe(3);
});

it('ignores the maintainer own sponsors without a sponsors org', function (): void {
    $maintainer = User::factory()->create();
    $sponsor = User::factory()->create();

    resolve(UpdateSponsorships::class)->handle($maintainer, new GitHubSponsors(true, sponsoring: null, sponsors: [githubSponsorship($sponsor)]));

    expect(Sponsorship::query()->count())->toBe(0);
});

it('keeps when a monthly sponsorship was first seen ended', function (): void {
    $maintainer = User::factory()->create();
    createSponsorsOrg($maintainer, from: SponsorsFrom::Everyone);
    $sponsor = User::factory()->create();
    $ended = new GitHubSponsors(true, sponsoring: [githubSponsorship($maintainer, isActive: false)], sponsors: null);

    resolve(UpdateSponsorships::class)->handle($sponsor, $ended);
    $this->travel(2)->days();
    resolve(UpdateSponsorships::class)->handle($sponsor, $ended);

    expect(Sponsorship::query()->sole()->lapses_at?->toDateString())->toBe(now()->subDays(2)->toDateString());
});

it('keeps someone who left out until they sponsor again', function (): void {
    $maintainer = User::factory()->create();
    $org = createSponsorsOrg($maintainer);
    $sponsor = User::factory()->create();
    Sponsorship::factory()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsor->id, 'left_at' => now()->subWeek()]);

    resolve(UpdateSponsorships::class)->handle($sponsor, new GitHubSponsors(true, sponsoring: [githubSponsorship($maintainer, sponsoredAt: now()->subMonth()->toImmutable())], sponsors: null));

    expect($org->membershipOf($sponsor))->toBeNull();

    resolve(UpdateSponsorships::class)->handle($sponsor, new GitHubSponsors(true, sponsoring: [githubSponsorship($maintainer, isOneTime: true, sponsoredAt: now()->subDay()->toImmutable())], sponsors: null));

    expect($org->membershipOf($sponsor))->not->toBeNull()
        ->and(Sponsorship::query()->sole()->left_at)->toBeNull();
});
