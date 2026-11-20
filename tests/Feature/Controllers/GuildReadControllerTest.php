<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Queries\JoinedGuildsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admins' => ['reader']]);
    $this->travelTo('2026-11-18 12:00:00');
    $this->reader = User::factory()->create(['handle' => 'reader']);
    $this->author = User::factory()->create(['handle' => 'author']);
});

function joinGuild(User $user, Guild $guild, string $joinedAt, ?string $readAt = null, ?string $mutedAt = null): void
{
    DB::table('guild_preferences')->insert(['user_id' => $user->id, 'guild_id' => $guild->id, 'joined_at' => $joinedAt, 'read_at' => $readAt, 'muted_at' => $mutedAt]);
}

it('counts the commits missed since the last read and sorts the guilds by that count', function (): void {
    $quiet = Guild::factory()->create(['handle' => 'aardvark', 'name' => 'Aardvark']);
    $busy = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust']);
    $hashtag = Guild::factory()->create(['handle' => 'php', 'name' => 'PHP']);
    joinGuild($this->reader, $quiet, '2026-11-01 00:00:00');
    joinGuild($this->reader, $busy, '2026-11-01 00:00:00', '2026-11-18 10:00:00');
    joinGuild($this->reader, $hashtag, '2026-11-01 00:00:00', '2026-11-18 10:00:00');

    Commit::factory()->for($this->author)->create(['guild_id' => $busy->id, 'body' => 'Read already', 'created_at' => '2026-11-18 09:00:00']);
    Commit::factory()->for($this->author)->count(2)->create(['guild_id' => $busy->id, 'body' => 'Board post', 'created_at' => '2026-11-18 11:00:00']);
    Commit::factory()->for($this->author)->create(['body' => 'Learning #php', 'created_at' => '2026-11-18 11:00:00']);
    Commit::factory()->for($this->author)->create(['body' => 'Only `#php` in code', 'created_at' => '2026-11-18 11:00:00']);
    Commit::factory()->for($this->reader)->create(['guild_id' => $busy->id, 'body' => 'My own post', 'created_at' => '2026-11-18 11:00:00']);

    expect(array_column(resolve(JoinedGuildsQuery::class)->get($this->reader), 'missed_commits', 'handle'))->toBe([
        'rust' => 2,
        'php' => 1,
        'aardvark' => 0,
    ]);
});

it('leaves out commits the reader cannot see or muted', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust']);
    joinGuild($this->reader, $guild, '2026-11-18 10:00:00');
    $muted = User::factory()->create();
    $this->reader->muting()->attach($muted);

    Commit::factory()->for($this->author)->create(['guild_id' => $guild->id, 'status' => CommitStatus::Pending, 'created_at' => '2026-11-18 11:00:00']);
    Commit::factory()->for($muted)->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 11:00:00']);

    expect(resolve(JoinedGuildsQuery::class)->get($this->reader)[0]['missed_commits'])->toBe(0);
});

it('counts from the last join after a guild is joined again', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust']);
    joinGuild($this->reader, $guild, '2026-11-18 11:30:00', '2026-11-01 00:00:00');
    Commit::factory()->for($this->author)->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 11:00:00']);

    expect(resolve(JoinedGuildsQuery::class)->get($this->reader)[0]['missed_commits'])->toBe(0);
});

it('lists no muted guild, and nothing without the guilds feature', function (): void {
    joinGuild($this->reader, Guild::factory()->create(), '2026-11-01 00:00:00', mutedAt: '2026-11-02 00:00:00');

    expect(resolve(JoinedGuildsQuery::class)->get($this->reader))->toBe([])
        ->and(resolve(JoinedGuildsQuery::class)->get($this->author))->toBe([]);
});

it('shares the joined guilds with the page', function (): void {
    $guild = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust']);
    joinGuild($this->reader, $guild, '2026-11-18 10:00:00');
    Commit::factory()->for($this->author)->create(['guild_id' => $guild->id, 'created_at' => '2026-11-18 11:00:00']);

    $this->actingAs($this->reader)->get(route('guild.show', 'rust'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('readFrom', '2026-11-18T10:00:00+00:00')
            ->where('joinedGuilds.0.handle', 'rust')
            ->where('joinedGuilds.0.missed_commits', 1));
});

it('gives a guild that the reader did not join no read marker', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->actingAs($this->reader)->get(route('guild.show', 'rust'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('readFrom', null));
});

it('updates the read time of a joined guild and tells the other tabs', function (): void {
    Event::fake([UsersUpdated::class]);
    $guild = Guild::factory()->create();
    joinGuild($this->reader, $guild, '2026-11-01 00:00:00');

    $this->actingAs($this->reader)->from(route('guild.show', $guild->handle))->put(route('guild-read.update', $guild->id))
        ->assertRedirect(route('guild.show', $guild->handle));

    expect(DB::table('guild_preferences')->where('user_id', $this->reader->id)->value('read_at'))->toBe('2026-11-18 12:00:00');
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$this->reader->id]);
});

it('writes nothing for a guild that the reader did not join', function (): void {
    Event::fake([UsersUpdated::class]);
    $guild = Guild::factory()->create();

    $this->actingAs($this->reader)->put(route('guild-read.update', $guild->id))->assertRedirect();

    expect(DB::table('guild_preferences')->count())->toBe(0);
    Event::assertNotDispatched(UsersUpdated::class);
});

it('hides the endpoint without the guilds feature, from guests, and for a missing guild', function (): void {
    $guild = Guild::factory()->create();

    $this->actingAs($this->author)->put(route('guild-read.update', $guild->id))->assertNotFound();
    $this->actingAs($this->reader)->put(route('guild-read.update', '019a0000-0000-7000-8000-000000000000'))->assertNotFound();
    auth()->logout();
    $this->put(route('guild-read.update', $guild->id))->assertRedirect();

    expect(DB::table('guild_preferences')->count())->toBe(0);
});
