<?php

declare(strict_types=1);

use App\Actions\DeleteOrganization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('deletes an organization with its admins, keeping the users', function (): void {
    $user = User::factory()->create();
    $organization = Profile::factory()->organization()->create();
    $kept = Profile::factory()->organization()->create();
    $organization->admins()->attach($user->id, ['created_at' => now()]);
    $kept->admins()->attach($user->id, ['created_at' => now()]);

    resolve(DeleteOrganization::class)->handle($organization);

    expect($organization->fresh())->toBeNull()
        ->and($user->fresh())->not->toBeNull()
        ->and(DB::table('organization_admins')->pluck('organization_id')->all())->toBe([$kept->id]);
});
