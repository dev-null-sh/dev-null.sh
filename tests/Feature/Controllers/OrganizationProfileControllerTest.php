<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('shows admins the profile of the organization in its settings, with its color', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['lane_color' => '#1a2b3c']);
    $organization->admins()->attach($user->id);

    $this->actingAs($user)
        ->get(route('organization-profile.edit', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-profile/edit')
            ->where('organization.id', $organization->id)
            ->where('role', 'admin')
            ->where('lane', '#1a2b3c')
            ->missing('organization.lane_color'));
});

it('hides the profile settings from members who are not admins, and from everyone else', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);

    $this->actingAs($member)->get(route('organization-profile.edit', $organization))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('organization-profile.edit', $organization))->assertNotFound();
});
