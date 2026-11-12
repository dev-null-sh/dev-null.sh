<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->member = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->member);
});

it('waits to follow a developer who is not here yet, and stops waiting', function (): void {
    $profile = Profile::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-pending-follow.store', $profile))->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-pending-follow.store', $profile))->assertNoContent();

    expect(DB::table('pending_follows')->where('github_id', $profile->github_id)->pluck('user_id')->all())->toBe([$this->member->id]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-pending-follow.destroy', $profile))->assertNoContent();

    $this->assertDatabaseEmpty('pending_follows');
});

it('waits only for developers who are not here yet', function (Closure $profile): void {
    $profile = $profile();

    signedApiRequest($this->keyPair, 'POST', route('api-pending-follow.store', $profile))->assertNotFound();
    signedApiRequest($this->keyPair, 'DELETE', route('api-pending-follow.destroy', $profile))->assertNotFound();

    $this->assertDatabaseEmpty('pending_follows');
})->with([
    'a user' => fn (): Profile => User::factory()->create()->profile,
    'an organization' => fn (): Profile => Organization::factory()->create()->profile,
]);

it('lets only signed requests wait', function (): void {
    $profile = Profile::factory()->create();

    $this->postJson(route('api-pending-follow.store', $profile))->assertUnauthorized();
    $this->deleteJson(route('api-pending-follow.destroy', $profile))->assertUnauthorized();

    $this->assertDatabaseEmpty('pending_follows');
});
