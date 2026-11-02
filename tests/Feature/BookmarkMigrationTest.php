<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('converts every bookmark while preserving existing stars and their timestamps', function (): void {
    Schema::create('bookmarks', function (Blueprint $table): void {
        $table->id();
        $table->uuid('user_id');
        $table->uuid('commit_id');
        $table->timestamps();
    });

    try {
        $user = User::factory()->create();
        $new = Commit::factory()->create();
        $existing = Commit::factory()->create();
        $user->starredCommits()->attach($existing, [
            'created_at' => '2026-10-01 12:00:00',
            'updated_at' => '2026-10-01 12:00:00',
        ]);
        DB::table('bookmarks')->insert([
            ['user_id' => $user->id, 'commit_id' => $new->id, 'created_at' => '2026-10-02 12:00:00', 'updated_at' => '2026-10-02 12:00:00'],
            ['user_id' => $user->id, 'commit_id' => $existing->id, 'created_at' => '2026-10-03 12:00:00', 'updated_at' => '2026-10-03 12:00:00'],
        ]);

        $migration = require database_path('migrations/2026_10_20_000001_convert_bookmarks_to_stars.php');
        $migration->up();

        expect(DB::table('stars')->where('user_id', $user->id)->count())->toBe(2)
            ->and(DB::table('stars')->where('user_id', $user->id)->where('commit_id', $new->id)->value('created_at'))
            ->toStartWith('2026-10-02 12:00:00')
            ->and(DB::table('stars')->where('user_id', $user->id)->where('commit_id', $existing->id)->value('created_at'))
            ->toStartWith('2026-10-01 12:00:00');
    } finally {
        Schema::dropIfExists('bookmarks');
    }
});
