<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('may follow an organization, which a follow again leaves as it was', function (): void {
    $this->freezeSecond();
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['handle' => 'laravel']);
    $user->followedOrganizations()->attach($organization, ['created_at' => now()->subDay()]);
    $other = Organization::factory()->create();

    $this->actingAs($user)
        ->from('/@laravel')
        ->post(route('organization-follow.store', $other))
        ->assertRedirect('/@laravel');

    $this->actingAs($user)->post(route('organization-follow.store', $organization));

    expect($user->followedOrganizations()->oldest('organization_follows.created_at')->get()->map(fn (Organization $followed): array => [$followed->id, $followed->pivot->created_at->toIso8601String()])->all())
        ->toBe([[$organization->id, now()->subDay()->toIso8601String()], [$other->id, now()->toIso8601String()]]);
});

it('may unfollow an organization', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $kept = Organization::factory()->create();
    $user->followedOrganizations()->attach([$organization->id, $kept->id]);
    $organization->followers()->attach(User::factory()->create());

    $this->actingAs($user)
        ->delete(route('organization-follow.destroy', $organization))
        ->assertRedirect();

    expect($user->followedOrganizations()->pluck('organizations.id')->all())->toBe([$kept->id])
        ->and($organization->followers()->count())->toBe(1);
});

it('does not follow a user through their profile', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organization-follow.store', $other->profile))
        ->assertNotFound();

    expect($user->followedOrganizations()->count())->toBe(0);
});

it('sends guests to sign in', function (): void {
    $organization = Organization::factory()->create();

    $this->post(route('organization-follow.store', $organization))->assertRedirect();

    expect($organization->followers()->count())->toBe(0);
});
