<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('leaves a handle held in several cases to the profile GitHub synced last, and makes handles unique whatever their case', function (): void {
    DB::statement('ALTER TABLE profiles ALTER COLUMN handle TYPE varchar(255)');
    DB::statement('CREATE INDEX profiles_lower_handle_index ON profiles (lower(handle))');
    $stale = User::factory()->create(['handle' => 'octocat', 'github_synced_at' => now()->subYear()]);
    $current = User::factory()->create(['handle' => 'OctoCat', 'github_synced_at' => now()]);
    $organization = Organization::factory()->create(['handle' => 'OCTOCAT', 'github_synced_at' => null]);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'handle' => 'tom', 'github_synced_at' => null]);
    $person = User::factory()->create(['handle' => 'Tom', 'github_synced_at' => now()]);
    $alone = User::factory()->create(['handle' => 'Laravel']);

    $migration = require database_path('migrations/2026_10_31_120000_make_profile_handles_case_insensitive.php');
    $migration->up();

    expect(Profile::query()->pluck('handle', 'github_id')->all())->toEqual([
        $stale->github_id => "_renamed-{$stale->github_id}",
        $current->github_id => 'OctoCat',
        $organization->profile->github_id => "_renamed-{$organization->profile->github_id}",
        $tom->github_id => CreateTomFriendship::FALLBACK_HANDLE,
        $person->github_id => 'Tom',
        $alone->github_id => 'Laravel',
    ])->and(Profile::query()->where('handle', 'octocat')->sole()->github_id)->toBe($current->github_id);
});
