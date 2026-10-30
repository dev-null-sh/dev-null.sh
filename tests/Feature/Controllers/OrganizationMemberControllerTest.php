<?php

declare(strict_types=1);

use App\Actions\DeleteOrganizationMember;
use App\Actions\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;

/**
 * @return array{0: Profile, 1: User, 2: User}
 */
function organizationWithAdminAndMember(): array
{
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization->admins()->attach($admin->id, ['created_at' => now()->subDay()]);
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member, 'created_at' => now()]);

    return [$organization, $admin, $member];
}

/**
 * @return array{organization: string, member: string}
 */
function memberRoute(Profile $organization, User $member): array
{
    return ['organization' => $organization->id, 'member' => $member->handle];
}

it('lists the members of the organization to its members, first joined first, with their roles', function (): void {
    $this->freezeSecond();
    [$organization, $admin, $member] = organizationWithAdminAndMember();

    $this->actingAs($member)
        ->get(route('organization-member.index', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-member/index')
            ->where('organization.id', $organization->id)
            ->where('role', 'member')
            ->has('members', 2)
            ->where('members.0.handle', $admin->handle)
            ->where('members.0.role', 'admin')
            ->where('members.0.joined_at', now()->subDay()->toJSON())
            ->where('members.1.handle', $member->handle)
            ->where('members.1.role', 'member')
            ->missing('members.0.pivot')
            ->missing('members.0.email'));
});

it('hides the members of the organization from everyone else', function (): void {
    [$organization] = organizationWithAdminAndMember();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('organization-member.index', $organization))->assertNotFound();
    $this->actingAs($user)->get(route('organization-member.index', $user->profile))->assertNotFound();
});

it('lets admins add a member by their handle, whatever its case and with its @', function (): void {
    [$organization, $admin] = organizationWithAdminAndMember();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('organization-member.index', $organization))
        ->post(route('organization-member.store', $organization), ['handle' => ' @'.mb_strtoupper($user->handle)])
        ->assertRedirect(route('organization-member.index', $organization))
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($user))->toBe(OrganizationRole::Member);
});

it('refuses to add someone who is not on /dev/null or already a member', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => ''])
        ->assertSessionHasErrors(['handle' => 'The handle field is required.']);

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => 'nobody-here'])
        ->assertSessionHasErrors(['handle' => "There's no @nobody-here on /dev/null."]);

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => $organization->handle])
        ->assertSessionHasErrors(['handle' => "There's no @laravel on /dev/null."]);

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => $member->handle])
        ->assertSessionHasErrors(['handle' => "@{$member->handle} is already a member."]);

    expect($organization->members()->count())->toBe(2);
});

it('refuses to add someone across a block, either way', function (): void {
    [$organization, $admin] = organizationWithAdminAndMember();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $admin->blocking()->attach($blocked->id);
    $blocker->blocking()->attach($admin->id);

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => $blocked->handle])
        ->assertSessionHasErrors(['handle' => "You can't add @{$blocked->handle}."]);

    $this->actingAs($admin)
        ->post(route('organization-member.store', $organization), ['handle' => $blocker->handle])
        ->assertSessionHasErrors(['handle' => "You can't add @{$blocker->handle}."]);

    expect($organization->members()->count())->toBe(2);
});

it('only lets admins add members', function (): void {
    [$organization, , $member] = organizationWithAdminAndMember();
    $user = User::factory()->create();

    $this->actingAs($member)->post(route('organization-member.store', $organization), ['handle' => $user->handle])->assertForbidden();
    $this->actingAs($user)->post(route('organization-member.store', $organization), ['handle' => $user->handle])->assertNotFound();

    expect($organization->roleOf($user))->toBeNull();
});

it('lets admins make a member an admin, and an admin a member again', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();

    $this->actingAs($admin)
        ->put(route('organization-member.update', memberRoute($organization, $member)), ['role' => 'admin'])
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($member))->toBe(OrganizationRole::Admin);

    $this->actingAs($member)
        ->put(route('organization-member.update', memberRoute($organization, $admin)), ['role' => 'member'])
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Member);
});

it('keeps the last admin of the organization', function (): void {
    [$organization, $admin] = organizationWithAdminAndMember();

    $this->actingAs($admin)
        ->put(route('organization-member.update', memberRoute($organization, $admin)), ['role' => 'member'])
        ->assertSessionHasErrors(['role' => '@laravel needs at least one admin.']);

    $this->actingAs($admin)
        ->delete(route('organization-member.destroy', memberRoute($organization, $admin)))
        ->assertSessionHasErrors(['member' => '@laravel needs at least one admin.']);

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('keeps the last admin when another admin was made a member after the request was allowed', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    app()->resolving(UpdateOrganizationMemberRole::class, fn () => $organization->members()->updateExistingPivot($admin->id, ['role' => OrganizationRole::Member]));

    $this->actingAs($admin)
        ->from(route('organization-member.index', $organization))
        ->put(route('organization-member.update', memberRoute($organization, $member)), ['role' => 'member'])
        ->assertRedirect(route('organization-member.index', $organization))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', '@laravel needs at least one admin.');

    expect($organization->roleOf($member))->toBe(OrganizationRole::Admin);
});

it('keeps the last admin when another admin left after the request was allowed', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    app()->resolving(DeleteOrganizationMember::class, fn () => $organization->members()->detach($member->id));

    $this->actingAs($admin)
        ->from(route('organization-member.index', $organization))
        ->delete(route('organization-member.destroy', memberRoute($organization, $admin)))
        ->assertRedirect(route('organization-member.index', $organization))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', '@laravel needs at least one admin.');

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('only lets admins change roles, of members', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();
    $user = User::factory()->create();

    $this->actingAs($member)->put(route('organization-member.update', memberRoute($organization, $member)), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($admin)->put(route('organization-member.update', memberRoute($organization, $user)), ['role' => 'admin'])->assertNotFound();
    $this->actingAs($admin)->put(route('organization-member.update', memberRoute($organization, $member)), ['role' => 'owner'])->assertSessionHasErrors('role');

    expect($organization->roleOf($member))->toBe(OrganizationRole::Member)
        ->and($organization->roleOf($user))->toBeNull();
});

it('never lets admins make themselves admins, which would undo another admin making them a member meanwhile', function (): void {
    [$organization, $admin] = organizationWithAdminAndMember();

    $this->actingAs($admin)
        ->put(route('organization-member.update', memberRoute($organization, $admin)), ['role' => 'admin'])
        ->assertForbidden();

    expect($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('lets admins remove members', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();

    $this->actingAs($admin)
        ->from(route('organization-member.index', $organization))
        ->delete(route('organization-member.destroy', memberRoute($organization, $member)))
        ->assertRedirect(route('organization-member.index', $organization));

    expect($organization->roleOf($member))->toBeNull();
});

it('lets members leave, back to the page of the organization', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();

    $this->actingAs($member)
        ->delete(route('organization-member.destroy', memberRoute($organization, $member)))
        ->assertRedirect(route('user.show', 'laravel'))
        ->assertInertiaFlash('toast.message', 'You left @laravel.');

    expect($organization->roleOf($member))->toBeNull()
        ->and($organization->roleOf($admin))->toBe(OrganizationRole::Admin);
});

it('lets an admin leave while another one stays', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();
    $organization->members()->updateExistingPivot($member->id, ['role' => OrganizationRole::Admin]);

    $this->actingAs($admin)
        ->delete(route('organization-member.destroy', memberRoute($organization, $admin)))
        ->assertSessionHasNoErrors();

    expect($organization->roleOf($admin))->toBeNull();
});

it('only lets admins remove someone else', function (): void {
    [$organization, $admin, $member] = organizationWithAdminAndMember();
    $user = User::factory()->create();

    $this->actingAs($member)->delete(route('organization-member.destroy', memberRoute($organization, $admin)))->assertForbidden();
    $this->actingAs($user)->delete(route('organization-member.destroy', memberRoute($organization, $member)))->assertNotFound();

    expect($organization->members()->count())->toBe(2);
});
