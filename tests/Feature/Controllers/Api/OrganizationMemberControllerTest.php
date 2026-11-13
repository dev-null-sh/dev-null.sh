<?php

declare(strict_types=1);

use App\Actions\DeleteOrganizationMember;
use App\Actions\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * @return array{0: Organization, 1: User, 2: User}
 */
function apiOrganizationWithAdminAndMember(): array
{
    $organization = Organization::factory()->create(['handle' => 'laravel']);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization->admins()->attach($admin->id, ['created_at' => now()->subDay()]);
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member, 'created_at' => now()]);

    return [$organization, $admin, $member];
}

/**
 * @return array{handle: string, member: string}
 */
function apiMemberRoute(User $member): array
{
    return ['handle' => 'laravel', 'member' => $member->handle];
}

it('lists the members of the organization to its members, first joined first, with their roles', function (): void {
    $this->freezeSecond();
    [, $admin, $member] = apiOrganizationWithAdminAndMember();

    signedApiRequest(apiKeyPairFor($member), 'GET', route('api-organization-member.index', 'laravel'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.handle', $admin->handle)
        ->assertJsonPath('data.0.role', 'admin')
        ->assertJsonPath('data.0.joined_at', now()->subDay()->toIso8601ZuluString())
        ->assertJsonPath('data.1.handle', $member->handle)
        ->assertJsonPath('data.1.role', 'member')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.pivot');
});

it('hides the members of the organization from everyone else', function (): void {
    apiOrganizationWithAdminAndMember();
    $keyPair = apiKeyPairFor($user = User::factory()->create());

    signedApiRequest($keyPair, 'GET', route('api-organization-member.index', 'laravel'))->assertNotFound();
    signedApiRequest($keyPair, 'GET', route('api-organization-member.index', $user->handle))->assertNotFound();
    signedApiRequest($keyPair, 'GET', route('api-organization-member.index', 'nobody'))->assertNotFound();
});

it('lets admins add a member by their handle, whatever its case and with its @', function (): void {
    [$organization, $admin] = apiOrganizationWithAdminAndMember();
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($admin), 'POST', route('api-organization-member.store', 'laravel'), ['handle' => ' @'.mb_strtoupper($user->handle)])
        ->assertCreated()
        ->assertJsonPath('data.handle', $user->handle)
        ->assertJsonPath('data.role', 'member');

    expect($organization->roleOf($user))->toBe(OrganizationRole::Member);
});

it('refuses to add someone who is not on /dev/null or already a member', function (string $handle, string $error): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();

    signedApiRequest(apiKeyPairFor($admin), 'POST', route('api-organization-member.store', 'laravel'), ['handle' => $handle === 'member' ? $member->handle : $handle])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['handle' => str_replace('@member', "@{$member->handle}", $error)]);

    expect($organization->members()->count())->toBe(2);
})->with([
    'empty' => ['', 'The handle field is required.'],
    'nobody' => ['nobody-here', "There's no @nobody-here on /dev/null."],
    'the organization' => ['laravel', "There's no @laravel on /dev/null."],
    'a member' => ['member', '@member is already a member.'],
]);

it('refuses to add someone across a block, either way', function (): void {
    [$organization, $admin] = apiOrganizationWithAdminAndMember();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $admin->blocking()->attach($blocked->id);
    $blocker->blocking()->attach($admin->id);

    foreach ([$blocked, $blocker] as $user) {
        signedApiRequest(apiKeyPairFor($admin), 'POST', route('api-organization-member.store', 'laravel'), ['handle' => $user->handle])
            ->assertJsonValidationErrors(['handle' => "You can't add @{$user->handle}."]);
    }

    expect($organization->members()->count())->toBe(2);
});

it('only lets admins add members', function (): void {
    [$organization, , $member] = apiOrganizationWithAdminAndMember();
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($member), 'POST', route('api-organization-member.store', 'laravel'), ['handle' => $user->handle])->assertForbidden();
    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-organization-member.store', 'laravel'), ['handle' => $user->handle])->assertNotFound();
    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-organization-member.store', 'nobody'), ['handle' => $user->handle])->assertNotFound();
    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-organization-member.store', $user->handle), ['handle' => $user->handle])->assertNotFound();

    expect($organization->roleOf($user))->toBeNull();
});

it('lets admins make a member an admin, and an admin a member again', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();

    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($member)), ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.handle', $member->handle)
        ->assertJsonPath('data.role', 'admin');

    signedApiRequest(apiKeyPairFor($member), 'PUT', route('api-organization-member.update', apiMemberRoute($admin)), ['role' => 'member'])
        ->assertOk()
        ->assertJsonPath('data.role', 'member');

    expect($organization->roleOf($member))->toBe(OrganizationRole::Admin)
        ->and($organization->roleOf($admin))->toBe(OrganizationRole::Member);
});

it('keeps the last admin of the organization', function (): void {
    [$organization, $admin] = apiOrganizationWithAdminAndMember();

    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($admin)), ['role' => 'member'])
        ->assertJsonValidationErrors(['role' => '@laravel needs at least one admin.']);

    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($admin)))
        ->assertJsonValidationErrors(['member' => '@laravel needs at least one admin.']);

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('keeps the last admin when another admin was made a member after the request was allowed', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    app()->resolving(UpdateOrganizationMemberRole::class, fn () => $organization->members()->updateExistingPivot($admin->id, ['role' => OrganizationRole::Member]));

    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($member)), ['role' => 'member'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role' => '@laravel needs at least one admin.']);

    expect($organization->roleOf($member))->toBe(OrganizationRole::Admin);
});

it('keeps the last admin when another admin left after the request was allowed', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    app()->resolving(DeleteOrganizationMember::class, fn () => $organization->members()->detach($member->id));

    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($admin)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['member' => '@laravel needs at least one admin.']);

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('only lets admins change roles, of members', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($member), 'PUT', route('api-organization-member.update', apiMemberRoute($member)), ['role' => 'admin'])->assertForbidden();
    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($user)), ['role' => 'admin'])->assertNotFound();
    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', ['handle' => 'nobody', 'member' => $member->handle]), ['role' => 'admin'])->assertNotFound();
    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', ['handle' => $admin->handle, 'member' => $member->handle]), ['role' => 'admin'])->assertNotFound();
    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($member)), ['role' => 'owner'])->assertJsonValidationErrors('role');

    expect($organization->roleOf($member))->toBe(OrganizationRole::Member)
        ->and($organization->roleOf($user))->toBeNull();
});

it('never lets admins make themselves admins, which would undo another admin making them a member meanwhile', function (): void {
    [$organization, $admin] = apiOrganizationWithAdminAndMember();

    signedApiRequest(apiKeyPairFor($admin), 'PUT', route('api-organization-member.update', apiMemberRoute($admin)), ['role' => 'admin'])->assertForbidden();

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('lets admins remove members, and members leave', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $other = User::factory()->create();
    $organization->members()->attach($other->id, ['role' => OrganizationRole::Member, 'created_at' => now()]);

    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($member)))->assertNoContent();
    signedApiRequest(apiKeyPairFor($other), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($other)))->assertNoContent();

    expect($organization->roleOf($member))->toBeNull()
        ->and($organization->roleOf($other))->toBeNull()
        ->and($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('lets an admin leave while another one stays', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($admin)))->assertNoContent();

    expect($organization->roleOf($admin))->toBeNull();
});

it('only lets admins remove someone else', function (): void {
    [$organization, $admin, $member] = apiOrganizationWithAdminAndMember();
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($member), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($admin)))->assertForbidden();
    signedApiRequest(apiKeyPairFor($user), 'DELETE', route('api-organization-member.destroy', apiMemberRoute($member)))->assertNotFound();
    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', ['handle' => 'nobody', 'member' => $member->handle]))->assertNotFound();
    signedApiRequest(apiKeyPairFor($admin), 'DELETE', route('api-organization-member.destroy', ['handle' => $admin->handle, 'member' => $member->handle]))->assertNotFound();

    expect($organization->members()->count())->toBe(2);
});

it('requires a signed request', function (): void {
    [, , $member] = apiOrganizationWithAdminAndMember();

    $this->getJson(route('api-organization-member.index', 'laravel'))->assertUnauthorized();
    $this->postJson(route('api-organization-member.store', 'laravel'), ['handle' => 'someone'])->assertUnauthorized();
    $this->putJson(route('api-organization-member.update', apiMemberRoute($member)), ['role' => 'admin'])->assertUnauthorized();
    $this->deleteJson(route('api-organization-member.destroy', apiMemberRoute($member)))->assertUnauthorized();
});
