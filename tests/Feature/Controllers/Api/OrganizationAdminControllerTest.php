<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use App\Models\User;

it('lets a public GitHub member claim an organization', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->once()
        ->with($organization->profile->github_id, $user->github_id)->andReturnTrue();

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-organization-admin.store', ['handle' => $organization->handle]))->assertNoContent();

    expect($organization->admins()->pluck('users.id')->all())->toBe([$user->id]);
});

it('refuses unknown, already claimed and nonpublic organization memberships', function (): void {
    $user = User::factory()->create();
    $key = apiKeyPairFor($user);
    $organization = Organization::factory()->create();
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->once()->andReturnFalse();

    signedApiRequest($key, 'POST', route('api-organization-admin.store', ['handle' => 'missing']))->assertNotFound();
    signedApiRequest($key, 'POST', route('api-organization-admin.store', ['handle' => $organization->handle]))->assertForbidden()
        ->assertJsonPath('message', "GitHub does not list you as a public member of @{$organization->handle}. On GitHub, make your membership of @{$organization->handle} public, then try again.");
    $admin = User::factory()->create();
    $organization->admins()->attach($admin);
    signedApiRequest($key, 'POST', route('api-organization-admin.store', ['handle' => $organization->handle]))->assertForbidden()
        ->assertJsonPath('message', "@{$organization->handle} was already claimed by @{$admin->handle}.");
    expect($organization->admins()->pluck('users.id')->all())->toBe([$admin->id]);
});

it('returns a conflict if someone else claims the organization during the GitHub check', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $organization = Organization::factory()->create();
    $this->mock(GitHubProvider::class)->shouldReceive('isPublicMember')->once()->andReturnUsing(function () use ($organization, $other): bool {
        $organization->admins()->attach($other);

        return true;
    });

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-organization-admin.store', ['handle' => $organization->handle]))
        ->assertConflict()->assertExactJson(['message' => 'Unable to claim this organization. Please try again.']);
    expect($organization->admins()->pluck('users.id')->all())->toBe([$other->id]);
});
