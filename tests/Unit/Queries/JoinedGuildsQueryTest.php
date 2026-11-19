<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Queries\JoinedGuildsQuery;
use App\Services\Features;
use Illuminate\Support\Facades\DB;

it('counts the missed commits of each guild from the read time of that guild', function (): void {
    $this->travelTo('2026-11-18 12:00:00');
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $readEarly = Guild::factory()->create(['handle' => 'early']);
    $readLate = Guild::factory()->create(['handle' => 'late']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $readEarly->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-01 00:00:00', 'read_at' => '2026-11-18 09:00:00'],
        ['guild_id' => $readLate->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-01 00:00:00', 'read_at' => '2026-11-18 11:00:00'],
    ]);
    Commit::factory()->create(['guild_id' => $readEarly->id, 'created_at' => '2026-11-18 10:00:00']);
    Commit::factory()->create(['guild_id' => $readLate->id, 'created_at' => '2026-11-18 10:00:00']);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'missed_commits', 'handle'))->toBe(['early' => 1, 'late' => 0]);
});

it('counts a commit written after the read time and no commit written at the read time', function (): void {
    $this->travelTo('2026-11-18 12:00:00');
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $readEarlier = Guild::factory()->create(['handle' => 'php']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $guild->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-01 00:00:00', 'read_at' => '2026-11-18 10:00:00'],
        ['guild_id' => $readEarlier->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-01 00:00:00', 'read_at' => '2026-11-18 08:00:00'],
    ]);
    Commit::factory()->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 09:59:59']);
    Commit::factory()->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 10:00:00']);
    Commit::factory()->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 10:00:01']);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'missed_commits', 'handle'))->toBe(['rust' => 1, 'php' => 0]);
});

it('counts a board post for its own guild only, whatever hashtag it carries', function (): void {
    $this->travelTo('2026-11-18 12:00:00');
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $php = Guild::factory()->create(['handle' => 'php']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $rust->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-18 09:00:00'],
        ['guild_id' => $php->id, 'user_id' => $reader->id, 'joined_at' => '2026-11-18 09:00:00'],
    ]);
    Commit::factory()->create(['guild_id' => $rust->id, 'body' => 'A board post about #php', 'created_at' => '2026-11-18 10:00:00']);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'missed_commits', 'handle'))->toBe(['rust' => 1, 'php' => 0]);
});
