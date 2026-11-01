<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * @return array{0: User, 1: Organization}
 */
function claimingUserAndOrganization(): array
{
    return [
        User::factory()->create(['github_id' => '720328']),
        Organization::factory()->create(['github_id' => '45861341', 'handle' => 'brefphp']),
    ];
}

it('makes a public member of the organization on github an admin of its page', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->with('45861341', '720328')->andReturnTrue();
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->post(route('organization-admin.store', $organization))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You are now an admin of @brefphp.']);

    expect($organization->admins()->pluck('users.id')->all())->toBe([$user->id]);
});

it('only claims from a form, so no other site can claim for the user', function (): void {
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)->get(route('organization-admin.store', $organization))->assertNotFound();
});

it('only lets users claim organizations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('organization-admin.store', $user->profile))->assertNotFound();
});

it('asks guests to sign in first', function (): void {
    $this->post(route('organization-admin.store', Organization::factory()->create()))->assertRedirect(route('home'));
});

it('does not ask github about a page someone already claimed', function (): void {
    $this->mock(GitHubProvider::class)->shouldNotReceive('isPublicMember');
    [$user, $organization] = claimingUserAndOrganization();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $organization->admins()->attach([$second->id => ['created_at' => now()], $first->id => ['created_at' => now()->subDay()]]);

    $this->actingAs($user)
        ->post(route('organization-admin.store', $organization))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed', ['message' => "@brefphp was already claimed by @{$first->handle}.", 'githubUrl' => null]);
});

it('refuses a member who loses the claim to another claiming at the same moment', function (): void {
    [$user, $organization] = claimingUserAndOrganization();
    $other = User::factory()->create();
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->andReturnUsing(function () use ($organization, $other): bool {
        $organization->admins()->attach($other);

        return true;
    });

    $this->actingAs($user)
        ->post(route('organization-admin.store', $organization))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed.message', "@brefphp was already claimed by @{$other->handle}.")
        ->assertInertiaFlashMissing('toast');

    expect($organization->admins()->pluck('users.id')->all())->toBe([$other->id]);
});

it('asks users github does not list as public members to make their membership public', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->with('45861341', '720328')->andReturnFalse();
    [$user, $organization] = claimingUserAndOrganization();

    $this->actingAs($user)
        ->post(route('organization-admin.store', $organization))
        ->assertRedirect('/@brefphp')
        ->assertInertiaFlash('claimFailed', [
            'message' => 'GitHub does not list you as a public member of @brefphp. On GitHub, make your membership of @brefphp public, then try again.',
            'githubUrl' => 'https://github.com/orgs/brefphp/people',
        ]);

    expect($organization->admins()->exists())->toBeFalse();
});

it('fails loudly when github fails', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->andThrow(new RequestException(new Response(new Psr7Response(502))));
    [$user, $organization] = claimingUserAndOrganization();

    $this->withoutExceptionHandling()
        ->actingAs($user)
        ->post(route('organization-admin.store', $organization));
})->throws(RequestException::class);
