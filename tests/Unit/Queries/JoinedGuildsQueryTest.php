<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
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

it('lists first the guild with the most commits of the reader, board posts and hashtags included', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $rust = Guild::factory()->create(['handle' => 'rust']);
    $php = Guild::factory()->create(['handle' => 'php']);
    $zig = Guild::factory()->create(['handle' => 'zig']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $rust->id, 'user_id' => $reader->id, 'joined_at' => now()],
        ['guild_id' => $php->id, 'user_id' => $reader->id, 'joined_at' => now()],
        ['guild_id' => $zig->id, 'user_id' => $reader->id, 'joined_at' => now()],
    ]);
    Commit::factory()->for($reader)->create(['guild_id' => $rust->id]);
    Commit::factory()->for($reader)->create(['body' => 'Learning #zig today']);
    Commit::factory()->for($reader)->create(['body' => 'More #zig today']);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'handle'))->toBe(['zig', 'rust', 'php']);
});

it('orders the guilds by the commits of the reader before the missed commits', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $quiet = Guild::factory()->create(['handle' => 'quiet']);
    $busy = Guild::factory()->create(['handle' => 'busy']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $quiet->id, 'user_id' => $reader->id, 'joined_at' => now()->subDay()],
        ['guild_id' => $busy->id, 'user_id' => $reader->id, 'joined_at' => now()->subDay()],
    ]);
    Commit::factory()->for($reader)->create(['guild_id' => $quiet->id]);
    Commit::factory()->count(3)->create(['guild_id' => $busy->id]);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'missed_commits', 'handle'))->toBe(['quiet' => 0, 'busy' => 3]);
});

it('orders guilds with equal commits of the reader by the missed commits, then by the handle', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $beta = Guild::factory()->create(['handle' => 'beta']);
    $alpha = Guild::factory()->create(['handle' => 'alpha']);
    $missed = Guild::factory()->create(['handle' => 'missed']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $beta->id, 'user_id' => $reader->id, 'joined_at' => now()->subDay()],
        ['guild_id' => $alpha->id, 'user_id' => $reader->id, 'joined_at' => now()->subDay()],
        ['guild_id' => $missed->id, 'user_id' => $reader->id, 'joined_at' => now()->subDay()],
    ]);
    Commit::factory()->create(['guild_id' => $missed->id]);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'handle'))->toBe(['missed', 'alpha', 'beta']);
});

it('leaves the commits of other people and the unapproved commits of the reader out of the order', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $alpha = Guild::factory()->create(['handle' => 'alpha']);
    $beta = Guild::factory()->create(['handle' => 'beta']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $alpha->id, 'user_id' => $reader->id, 'joined_at' => now()],
        ['guild_id' => $beta->id, 'user_id' => $reader->id, 'joined_at' => now()],
    ]);
    Commit::factory()->for(User::factory())->create(['guild_id' => $beta->id, 'created_at' => now()->subDay()]);
    Commit::factory()->for($reader)->create(['guild_id' => $beta->id, 'status' => CommitStatus::Rejected]);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($reader), 'handle'))->toBe(['alpha', 'beta']);
});

it('reads the order with the same number of queries for more commits of the reader', function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $reader = User::factory()->create();
    $guild = Guild::factory()->create(['handle' => 'rust']);
    DB::table('guild_preferences')->insert(['guild_id' => $guild->id, 'user_id' => $reader->id, 'joined_at' => now()]);
    Commit::factory()->for($reader)->create(['guild_id' => $guild->id]);
    $query = resolve(JoinedGuildsQuery::class);
    $query->get($reader);

    DB::enableQueryLog();
    $query->get($reader);
    $queriesForOneCommit = count(DB::getQueryLog());
    DB::flushQueryLog();

    Commit::factory()->for($reader)->count(3)->create(['guild_id' => $guild->id]);
    DB::flushQueryLog();
    $query->get($reader);

    expect(count(DB::getQueryLog()))->toBe($queriesForOneCommit);
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
