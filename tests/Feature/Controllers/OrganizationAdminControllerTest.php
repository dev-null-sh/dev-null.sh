<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

/**
 * GitHub sends the user back as the given account, having granted the read:org scope.
 */
function fakeClaimingGitHubAccount(string $id): void
{
    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('redirectUrl')->with(route('organization-admin.store'))->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(GitHubUser::fake(['id' => $id, 'nickname' => 'octocat', 'token' => 'claim-token']));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

/**
 * @param  array<string, mixed>  $membership
 * @param  array<string, string>  $headers
 */
function fakeGitHubMembership(array $membership = [], int $status = 200, array $headers = []): void
{
    Http::fake([
        'api.github.com/user/memberships/orgs/brefphp' => Http::response([
            'state' => 'active',
            'role' => 'member',
            'organization' => ['id' => 45861341],
            ...$membership,
        ], $status, $headers),
    ]);
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

it('makes a member of the organization on github an admin of its page', function (string $role): void {
    fakeClaimingGitHubAccount('720328');
    fakeGitHubMembership(['role' => $role]);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store', ['code' => 'code', 'state' => 'state']))
        ->assertRedirect('/@brefphp')
        ->assertSessionMissing('claiming-organization')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You are now an admin of @brefphp.']);

    expect($organization->admins()->pluck('users.id')->all())->toBe([$user->id]);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer claim-token'));
})->with(['admin', 'member']);

it('keeps the first claim when the user claims again', function (): void {
    fakeClaimingGitHubAccount('720328');
    fakeGitHubMembership();
    [$user, $organization] = claimingUserAndOrganization();
    $claimedAt = now()->subWeek()->startOfSecond();
    DB::table('organization_admins')->insert(['organization_id' => $organization->id, 'user_id' => $user->id, 'created_at' => $claimedAt]);

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp');

    expect(DB::table('organization_admins')->sole()->created_at)->toBe($claimedAt->toDateTimeString());
});

it('refuses users github does not say are members', function (array $membership, int $status): void {
    fakeClaimingGitHubAccount('720328');
    fakeGitHubMembership($membership, $status);
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertSessionMissing('claiming-organization')
        ->assertInertiaFlash('claimFailed', ['message' => 'GitHub says you are not a member of @brefphp.', 'approvalUrl' => null]);

    expect($organization->admins()->exists())->toBeFalse();
})->with([
    'not a member' => [['message' => 'Not Found'], 404],
    'invited only' => [['state' => 'pending'], 200],
    'a billing manager' => [['role' => 'billing_manager'], 200],
    'of another organization since renamed' => [['organization' => ['id' => 1]], 200],
]);

it('says where to approve /dev/null when the organization hides its members from it', function (): void {
    config(['services.github.client_id' => 'client-id']);
    fakeClaimingGitHubAccount('720328');
    fakeGitHubMembership(['message' => 'Although you appear to have the correct authorization credentials, the `brefphp` organization has enabled OAuth App access restrictions.'], 403);
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
    fakeGitHubMembership(['message' => 'Resource protected by organization SAML enforcement. You must grant your OAuth token access to this organization.'], 403, ['X-GitHub-SSO' => 'required; url=https://github.com/orgs/brefphp/sso?authorization_request=abc']);
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

it('fails loudly when github fails', function (array $membership, int $status): void {
    fakeClaimingGitHubAccount('720328');
    fakeGitHubMembership($membership, $status);
    [$user, $organization] = claimingUserAndOrganization();

    $this->withoutExceptionHandling()
        ->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'));
})->with([
    'down' => [[], 502],
    'rate limited' => [['message' => 'API rate limit exceeded for user ID 720328.'], 403],
])->throws(RequestException::class);

it('refuses a github account other than the one the user signed in with', function (): void {
    Http::fake();
    fakeClaimingGitHubAccount('583231');
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->withSession(['claiming-organization' => $organization->id])
        ->get(route('organization-admin.store'))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', "GitHub is signed in as @octocat. Sign in there as @{$user->profile->handle} to claim this page.");

    expect($organization->admins()->exists())->toBeFalse();
    Http::assertNothingSent();
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
    $provider = Mockery::mock(GithubProvider::class);
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
