<?php

declare(strict_types=1);

use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('copies the github identity of the users without a profile, once', function (): void {
    $existing = User::factory()->create(['handle' => 'kept']);
    $id = Str::uuid7()->toString();

    // A user signed up by the code that still wrote their identity on the users table...
    DB::table('users')->insert([
        'id' => $id,
        'github_id' => '583231',
        'username' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
        'github_synced_at' => '2026-10-24 12:00:00',
        'created_at' => '2026-10-24 11:00:00',
        'updated_at' => '2026-10-24 12:00:00',
    ]);

    $migration = require database_path('migrations/2026_10_25_180001_copy_the_github_identity_of_users_to_profiles.php');
    $migration->up();
    $migration->up();

    expect(Profile::query()->count())->toBe(2)
        ->and($existing->profile->refresh()->handle)->toBe('kept')
        ->and(User::query()->findOrFail($id)->profile)
        ->type->toBe(ProfileType::User)
        ->handle->toBe('octocat')
        ->name->toBe('The Octocat')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231')
        ->bio->toBe('I am the Octocat.')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->github_synced_at->toDateTimeString()->toBe('2026-10-24 12:00:00')
        ->created_at->toDateTimeString()->toBe('2026-10-24 11:00:00');
});
