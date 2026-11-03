<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lets a member wait to follow a developer who is not here yet, and stop waiting', function (): void {
    $member = User::factory()->create();
    $profile = Profile::factory()->create();

    $this->actingAs($member)->from('/@'.$profile->handle)->post(route('pending-follow.store', $profile))->assertRedirect('/@'.$profile->handle);
    $this->actingAs($member)->post(route('pending-follow.store', $profile))->assertRedirect();

    expect(DB::table('pending_follows')->where('github_id', $profile->github_id)->pluck('user_id')->all())->toBe([$member->id]);

    $this->actingAs($member)->delete(route('pending-follow.destroy', $profile))->assertRedirect();

    $this->assertDatabaseEmpty('pending_follows');
});

it('waits only for developers who are not here yet', function (Closure $profile): void {
    $member = User::factory()->create();
    $profile = $profile();

    $this->actingAs($member)->post(route('pending-follow.store', $profile))->assertNotFound();
    $this->actingAs($member)->delete(route('pending-follow.destroy', $profile))->assertNotFound();

    $this->assertDatabaseEmpty('pending_follows');
})->with([
    'a user' => fn (): Profile => User::factory()->create()->profile,
    'an organization' => fn (): Profile => Organization::factory()->create()->profile,
]);

it('lets only members wait', function (): void {
    $profile = Profile::factory()->create();

    $this->post(route('pending-follow.store', $profile))->assertRedirect(route('home'));

    $this->assertDatabaseEmpty('pending_follows');
});
