<?php

declare(strict_types=1);

use App\Actions\UpdateOrCreateOrganization;
use App\Enums\ProfileType;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('updates the organization with the same GitHub ID when it is renamed', function (): void {
    $organization = Organization::factory()->create(['github_id' => '958072', 'handle' => 'laravel-old', 'github_synced_at' => now()->subWeek()]);

    $updated = resolve(UpdateOrCreateOrganization::class)->handle([
        'login' => 'laravel',
        'id' => 958072,
        'name' => null,
        'avatar_url' => null,
        'bio' => null,
        'location' => 'Little Rock',
        'blog' => null,
    ]);

    expect($updated->id)->toBe($organization->id)
        ->and($organization->refresh())
        ->handle->toBe('laravel')
        ->name->toBe('laravel')
        ->avatar_url->toBeNull()
        ->location->toBe('Little Rock')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

it('keeps the color its admins chose when it syncs again', function (): void {
    $organization = Organization::factory()->create(['github_id' => '958072', 'handle' => 'laravel', 'lane_color' => '#1a2b3c']);

    resolve(UpdateOrCreateOrganization::class)->handle(['login' => 'laravel', 'id' => 958072, 'bio' => 'Updated on GitHub.']);

    expect($organization->refresh())
        ->bio->toBe('Updated on GitHub.')
        ->lane_color->toBe('#1a2b3c');
});

it('gives the handle up when another profile renamed away from it on GitHub', function (): void {
    $stale = Organization::factory()->create(['github_id' => '1', 'handle' => 'Laravel']);
    $other = Organization::factory()->create(['github_id' => '2', 'handle' => 'symfony']);

    $organization = resolve(UpdateOrCreateOrganization::class)->handle(['login' => 'laravel', 'id' => 958072]);

    expect($organization->handle)->toBe('laravel')
        ->and($stale->refresh()->handle)->toBe('_renamed-1')
        ->and($other->refresh()->handle)->toBe('symfony');
});

it("fails rather than turn a user's profile into an organization, which GitHub allows but /dev/null does not handle", function (): void {
    $profile = Profile::factory()->create(['github_id' => '958072', 'handle' => 'laravel']);

    expect(fn () => resolve(UpdateOrCreateOrganization::class)->handle(['login' => 'laravel', 'id' => 958072]))
        ->toThrow(UniqueConstraintViolationException::class);

    expect($profile->refresh()->type)->toBe(ProfileType::User)
        ->and(Organization::query()->exists())->toBeFalse();
});

it('is followed, once created, by the users following it on GitHub whose follows were imported before', function (): void {
    [$follower, $other] = User::factory()->count(2)->create();
    DB::table('pending_organization_follows')->insert([
        ['user_id' => $follower->id, 'github_id' => '958072', 'created_at' => now()],
        ['user_id' => $other->id, 'github_id' => '1', 'created_at' => now()],
    ]);

    $organization = resolve(UpdateOrCreateOrganization::class)->handle(['login' => 'laravel', 'id' => 958072]);

    expect($organization->followers()->pluck('users.id')->all())->toBe([$follower->id])
        ->and(DB::table('pending_organization_follows')->pluck('user_id')->all())->toBe([$other->id]);
});
