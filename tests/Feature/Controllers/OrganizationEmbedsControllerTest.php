<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('lets an admin choose whether others may embed the commits of the organization', function (bool $allowed): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['allows_embeds' => ! $allowed]);
    $organization->admins()->attach($user->id, ['created_at' => now()]);

    $this->actingAs($user)
        ->fromRoute('organization-profile.edit', $organization)
        ->put(route('organization-embeds.update', $organization), ['enabled' => $allowed])
        ->assertRedirectToRoute('organization-profile.edit', $organization)
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Embeds preference saved.']);

    expect($organization->refresh()->allows_embeds)->toBe($allowed)
        ->and($organization->toArray())->not->toHaveKey('allows_embeds');

    $this->get(route('organization-profile.edit', $organization))
        ->assertInertia(fn ($page) => $page->where('allowsEmbeds', $allowed));
})->with([true, false]);

it('allows embeds of organizations by default', function (): void {
    expect(Organization::factory()->create()->refresh()->allows_embeds)->toBeTrue();
});

it('hides the setting from members who are not admins, and from everyone else', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);

    $this->actingAs($member)->put(route('organization-embeds.update', $organization), ['enabled' => false])->assertNotFound();
    $this->actingAs(User::factory()->create())->put(route('organization-embeds.update', $organization), ['enabled' => false])->assertNotFound();

    expect($organization->refresh()->allows_embeds)->toBeTrue();
});

it('rejects an invalid preference', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->admins()->attach($user->id, ['created_at' => now()]);

    $this->actingAs($user)
        ->put(route('organization-embeds.update', $organization), ['enabled' => 'invalid'])
        ->assertSessionHasErrors('enabled');

    expect($organization->refresh()->allows_embeds)->toBeTrue();
});
