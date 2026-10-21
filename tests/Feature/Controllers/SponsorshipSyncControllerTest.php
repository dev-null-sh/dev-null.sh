<?php

declare(strict_types=1);

use App\Enums\OrgRole;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

it('goes to github', function (): void {
    Socialite::fake('github');

    $this->actingAs(User::factory()->create())->get(route('sponsorship-sync.create'))->assertRedirect();
});

it('lets in the sponsors github names, with a token it does not keep', function (): void {
    $maintainer = User::factory()->create();
    $org = createSponsorsOrg($maintainer);
    $sponsor = User::factory()->create();
    fakeGitHubSponsors(sponsors: [sponsorshipNode($sponsor)]);
    Socialite::fake('github', GitHubUser::fake(['id' => $maintainer->github_id, 'token' => 'gho_maintainer']));

    $this->actingAs($maintainer)->get(route('sponsorship-sync.store'))->assertRedirectToRoute('org.show', $org);

    expect($org->roleOf($sponsor))->toBe(OrgRole::Contributor);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer gho_maintainer'));
});

it('syncs nothing when github says no, or hands back someone else', function (Closure $githubUser, array $query): void {
    $maintainer = User::factory()->create();
    createSponsorsOrg($maintainer);
    Http::fake();
    Socialite::fake('github', $githubUser);

    $this->actingAs($maintainer)->get(route('sponsorship-sync.store', $query))->assertRedirectToRoute('user-profile.edit');

    Http::assertNothingSent();
})->with([
    'denied' => [fn () => GitHubUser::fake([]), ['error' => 'access_denied']],
    'stale state' => [fn () => throw new InvalidStateException(), []],
    'another account' => [fn () => GitHubUser::fake(['id' => '1', 'token' => 'gho_other']), []],
]);

it('says so when github cannot answer', function (): void {
    $maintainer = User::factory()->create();
    createSponsorsOrg($maintainer);
    Http::fake(['api.github.com/graphql' => Http::response(status: 502)]);
    Socialite::fake('github', GitHubUser::fake(['id' => $maintainer->github_id, 'token' => 'gho_maintainer']));

    $this->actingAs($maintainer)->get(route('sponsorship-sync.store'))->assertRedirectToRoute('user-profile.edit');
});

it('needs a sponsors org', function (): void {
    $this->actingAs(User::factory()->create())->get(route('sponsorship-sync.store'))->assertNotFound();
});
