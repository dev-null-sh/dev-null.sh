<?php

declare(strict_types=1);

use App\Actions\CreateOrganizationAdmin;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('makes only the first of two members coming back from github at once the admin', function (): void {
    $organization = Organization::factory()->create();
    [$first, $second] = User::factory()->count(2)->create();

    expect(resolve(CreateOrganizationAdmin::class)->handle($organization, $first))->toBeTrue()
        ->and(resolve(CreateOrganizationAdmin::class)->handle($organization, $second))->toBeFalse()
        ->and($organization->admins()->pluck('users.id')->all())->toBe([$first->id]);
});

it('makes a member the admin of a page left without one', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);

    expect(resolve(CreateOrganizationAdmin::class)->handle($organization, $member))->toBeTrue()
        ->and($organization->roleOf($member))->toBe(OrganizationRole::Admin)
        ->and($organization->members()->count())->toBe(1);
});
