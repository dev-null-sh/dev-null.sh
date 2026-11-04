<?php

declare(strict_types=1);

use App\Models\User;
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
