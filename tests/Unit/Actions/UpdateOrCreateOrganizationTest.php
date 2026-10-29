<?php

declare(strict_types=1);

use App\Actions\UpdateOrCreateOrganization;
use App\Models\Profile;

it('updates the organization with the same GitHub ID when it is renamed', function (): void {
    $organization = Profile::factory()->organization()->create(['github_id' => '958072', 'handle' => 'laravel-old', 'github_synced_at' => now()->subWeek()]);

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

it('gives the handle up when another profile renamed away from it on GitHub', function (): void {
    $stale = Profile::factory()->organization()->create(['github_id' => '1', 'handle' => 'Laravel']);
    $other = Profile::factory()->organization()->create(['github_id' => '2', 'handle' => 'symfony']);

    $organization = resolve(UpdateOrCreateOrganization::class)->handle(['login' => 'laravel', 'id' => 958072]);

    expect($organization->handle)->toBe('laravel')
        ->and($stale->refresh()->handle)->toBe('_renamed-1')
        ->and($other->refresh()->handle)->toBe('symfony');
});
