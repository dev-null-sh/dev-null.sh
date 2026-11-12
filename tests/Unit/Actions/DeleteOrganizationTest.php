<?php

declare(strict_types=1);

use App\Actions\DeleteOrganization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('deletes an organization with its profile, members and followers, keeping the users', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $kept = Organization::factory()->create();
    $organization->members()->attach($user->id, ['role' => OrganizationRole::Member]);
    $kept->admins()->attach($user->id, ['created_at' => now()]);
    $user->followedOrganizations()->attach([$organization->id, $kept->id]);

    resolve(DeleteOrganization::class)->handle($organization);

    expect($organization->fresh())->toBeNull()
        ->and(Profile::query()->find($organization->id))->toBeNull()
        ->and($user->fresh())->not->toBeNull()
        ->and(DB::table('organization_members')->pluck('organization_id')->all())->toBe([$kept->id])
        ->and(DB::table('organization_follows')->pluck('organization_id')->all())->toBe([$kept->id]);
});
