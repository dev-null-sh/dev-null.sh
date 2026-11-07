<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('starts the active days with the last day each person was seen', function (): void {
    Schema::drop('user_active_days');
    $seen = User::factory()->create(['last_seen_at' => '2026-10-30']);
    User::factory()->create(['last_seen_at' => null]);

    $migration = require database_path('migrations/2026_10_30_120000_create_user_active_days_table.php');
    $migration->up();

    expect(DB::table('user_active_days')->get(['user_id', 'seen_at'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([['user_id' => $seen->id, 'seen_at' => '2026-10-30']]);
});

it('counts past days as web, and days to come as no app until one is seen', function (): void {
    Schema::table('user_active_days', fn (Blueprint $table) => $table->dropColumn(['web_app', 'native_mobile_app', 'mac_app', 'other_app']));
    $past = User::factory()->create();
    $next = User::factory()->create();
    DB::table('user_active_days')->insert(['user_id' => $past->id, 'seen_at' => '2026-11-05']);

    $migration = require database_path('migrations/2026_11_06_120000_add_apps_to_user_active_days_table.php');
    $migration->up();
    DB::table('user_active_days')->insert(['user_id' => $next->id, 'seen_at' => '2026-11-06']);

    expect(DB::table('user_active_days')->oldest('seen_at')->get(['web_app', 'native_mobile_app', 'mac_app', 'other_app'])->map(fn (object $row): array => (array) $row)->all())->toBe([
        ['web_app' => true, 'native_mobile_app' => false, 'mac_app' => false, 'other_app' => false],
        ['web_app' => false, 'native_mobile_app' => false, 'mac_app' => false, 'other_app' => false],
    ]);
});
