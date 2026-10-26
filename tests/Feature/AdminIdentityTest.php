<?php

declare(strict_types=1);

use App\Models\User;

it('matches an admin by GitHub ID rather than by handle', function (): void {
    config(['app.admin_github_ids' => ['42'], 'app.admins' => []]);

    expect(User::factory()->create(['github_id' => '42', 'handle' => 'renamed-away'])->isAdmin())->toBeTrue()
        ->and(User::factory()->create(['github_id' => '43', 'handle' => 'admin'])->isAdmin())->toBeFalse();
});

it('gives nothing to whoever claims the handle a former admin renamed away from', function (): void {
    config(['app.admin_github_ids' => ['42'], 'app.admins' => ['admin']]);

    expect(User::factory()->create(['github_id' => '43', 'handle' => 'admin'])->isAdmin())->toBeFalse();
});

it('falls back to handles while no IDs are configured', function (): void {
    config(['app.admin_github_ids' => [], 'app.admins' => ['admin']]);

    expect(User::factory()->create(['handle' => 'admin'])->isAdmin())->toBeTrue()
        ->and(User::factory()->create(['handle' => 'someone'])->isAdmin())->toBeFalse();
});
