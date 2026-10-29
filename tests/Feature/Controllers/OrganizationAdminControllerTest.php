<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Enums\GitHubOrganizationMembership;
use App\Models\Profile;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider as GitHubOAuthProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

/**
 * GitHub sends the user back as the given account, having granted the read:org scope.
 */
function fakeClaimingGitHubAccount(string $id): void
{
    $provider = Mockery::mock(GitHubOAuthProvider::class);
    $provider->shouldReceive('redirectUrl')->with(route('organization-admin.store'))->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(GitHubUser::fake(['id' => $id, 'nickname' => 'octocat', 'token' => 'claim-token']));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

/**
 * @return array{0: User, 1: Profile}
 */
function claimingUserAndOrganization(): array
{
    return [
        User::factory()->create(['github_id' => '720328']),
        Profile::factory()->organization()->create(['github_id' => '45861341', 'handle' => 'brefphp']),
    ];
}

it('asks github for the organizations of the user, coming back to the claim', function (): void {
    config(['services.github.client_id' => 'client-id']);
    [$user, $organization] = claimingUserAndOrganization();

    $response = $this->actingAs($user)->post(route('organization-admin.create', $organization));

    $location = $response->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://github.com/login/oauth/authorize?')
        ->and($query)->toMatchArray([
            'client_id' => 'client-id',
            'redirect_uri' => route('organization-admin.store'),
            'scope' => 'user:email,read:org',
        ]);
    $response->assertSessionHas('claiming-organization', $organization->id);
});

it('leaves the page for github from the claim button', function (): void {
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->post(route('organization-admin.create', $organization), headers: ['X-Inertia' => 'true'])
        ->assertConflict()
        ->assertHeader('X-Inertia-Location');
});

it('only starts a claim from a form, so no other site can start one for the user', function (): void {
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)->get(route('organization-admin.create', $organization))
        ->assertNotFound()
        ->assertSessionMissing('claiming-organization');
});

it('only lets users claim organizations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('organization-admin.create', $user->profile))->assertNotFound();
});

it('asks guests to sign in first', function (): void {
    $this->post(route('organization-admin.create', Profile::factory()->organization()->create()))->assertRedirect(route('home'));
});

it('makes a member of the organization on github an admin of its page', function (): void {
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->with('claim-token', 'brefphp', '45861341')->andReturn(GitHubOrganizationMembership::Member);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store', ['code' => 'code', 'state' => 'state']))
        ->assertRedirect('/@brefphp')
        ->assertSessionMissing('claiming-organization')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You are now an admin of @brefphp.']);

    expect($organization->admins()->pluck('users.id')->all())->toBe([$user->id]);
});

it('does not send anyone to github for a page someone already claimed', function (): void {
    [$user, $organization] = claimingUserAndOrganization();
    $admin = User::factory()->create();
    $organization->admins()->attach($admin);

    $this->actingAs($user)
        ->post(route('organization-admin.create', $organization))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed', ['message' => "@brefphp was already claimed by @{$admin->handle}.", 'approvalUrl' => null]);
});

it('refuses a claim when someone else claimed the page while the user was on github', function (): void {
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldNotReceive('organizationMembership');
    [$user, $organization] = claimingUserAndOrganization();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $organization->admins()->attach([$second->id => ['created_at' => now()], $first->id => ['created_at' => now()->subDay()]]);

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store', ['code' => 'code', 'state' => 'state']))
        ->assertRedirect('/@brefphp')
        ->assertSessionMissing('claiming-organization')
        ->assertInertiaFlash('claimFailed.message', "@brefphp was already claimed by @{$first->handle}.");

    expect($organization->admins()->pluck('users.id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('refuses a member who loses the claim to another coming back from github at the same moment', function (): void {
    fakeClaimingGitHubAccount('720328');
    [$user, $organization] = claimingUserAndOrganization();
    $other = User::factory()->create();
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->andReturnUsing(function () use ($organization, $other): GitHubOrganizationMembership {
        $organization->admins()->attach($other);

        return GitHubOrganizationMembership::Member;
    });

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', "@brefphp was already claimed by @{$other->handle}.")
        ->assertInertiaFlashMissing('toast');

    expect($organization->admins()->pluck('users.id')->all())->toBe([$other->id]);
});

it('refuses users github does not say are members', function (): void {
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->with('claim-token', 'brefphp', '45861341')->andReturn(GitHubOrganizationMembership::NotMember);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertSessionMissing('claiming-organization')
        ->assertInertiaFlash('claimFailed', ['message' => 'GitHub says you are not a member of @brefphp.', 'approvalUrl' => null]);

    expect($organization->admins()->exists())->toBeFalse();
});

it('says where to approve /dev/null when the organization hides its members from it', function (): void {
    config(['services.github.client_id' => 'client-id']);
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->with('claim-token', 'brefphp', '45861341')->andReturn(GitHubOrganizationMembership::Restricted);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.approvalUrl', 'https://github.com/settings/connections/applications/client-id');

    expect($organization->admins()->exists())->toBeFalse();
});

it('says where to sign in when the organization uses single sign-on', function (): void {
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->with('claim-token', 'brefphp', '45861341')->andReturn(GitHubOrganizationMembership::SingleSignOn);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed', [
            'message' => '@brefphp signs its members in with single sign-on. Sign in to @brefphp on GitHub, then try again.',
            'approvalUrl' => 'https://github.com/orgs/brefphp/sso',
        ]);

    expect($organization->admins()->exists())->toBeFalse();
});

it('fails loudly when github fails', function (): void {
    fakeClaimingGitHubAccount('720328');
    $this->mock(GitHubProvider::class)->shouldReceive('organizationMembership')->andThrow(new RequestException(new Response(new Psr7Response(502))));
    [$user, $organization] = claimingUserAndOrganization();

    $this->withoutExceptionHandling()
        ->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'));
})->throws(RequestException::class);

it('refuses a github account other than the one the user signed in with', function (): void {
    fakeClaimingGitHubAccount('583231');
    $this->mock(GitHubProvider::class)->shouldNotReceive('organizationMembership');
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', "GitHub is signed in as @octocat. Sign in there as @{$user->profile->handle} to claim this page.");

    expect($organization->admins()->exists())->toBeFalse();
});

it('fails when the user denies access on github', function (): void {
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store', ['error' => 'access_denied']))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', 'GitHub was not asked about your organizations, so the page was not claimed.');
});

it('fails when the state does not match', function (): void {
    $provider = Mockery::mock(GitHubOAuthProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', 'Unable to claim this organization. Please try again.');
});

it('fails when no organization is being claimed', function (): void {
    [$user] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->get(route('organization-admin.store'))
        ->assertRedirect(route('log.show', $user->log))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Unable to claim this organization. Please try again.']);
});
