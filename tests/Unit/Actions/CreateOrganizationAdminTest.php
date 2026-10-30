<?php

declare(strict_types=1);

use App\Actions\CreateOrganizationAdmin;
use App\Models\Profile;
use App\Models\User;

it('makes only the first of two members coming back from github at once the admin', function (): void {
    $organization = Profile::factory()->organization()->create();
    [$first, $second] = User::factory()->count(2)->create();

    expect(resolve(CreateOrganizationAdmin::class)->handle($organization, $first))->toBeTrue()
        ->and(resolve(CreateOrganizationAdmin::class)->handle($organization, $second))->toBeFalse()
        ->and($organization->admins()->pluck('users.id')->all())->toBe([$first->id]);
});
