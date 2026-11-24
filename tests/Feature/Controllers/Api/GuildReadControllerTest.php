<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $this->travelTo('2026-11-18 12:00:00');

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->guild = Guild::factory()->create(['handle' => 'rust']);
});

it('marks a joined guild read, clears its missed commits and tells the other clients of the user', function (): void {
    DB::table('guild_preferences')->insert(['user_id' => $this->user->id, 'guild_id' => $this->guild->id, 'joined_at' => '2026-11-01 00:00:00']);
    Commit::factory()->for(User::factory())->create(['guild_id' => $this->guild->id, 'created_at' => '2026-11-18 11:00:00']);

    signedApiRequest($this->keyPair, 'GET', route('api-joined-guild.index'))->assertJsonPath('data.0.missed_commits', 1);

    Event::fake([UsersUpdated::class]);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-read.update', 'rust'))->assertNoContent();

    expect(DB::table('guild_preferences')->where('user_id', $this->user->id)->value('read_at'))->toBe('2026-11-18 12:00:00');
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && $event->userIds === [$this->user->id]);

    signedApiRequest($this->keyPair, 'GET', route('api-joined-guild.index'))
        ->assertJsonPath('data.0.missed_commits', 0)
        ->assertJsonPath('data.0.read_from', '2026-11-18T12:00:00Z');
});

it('writes nothing for a guild the user did not join', function (): void {
    Event::fake([UsersUpdated::class]);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-read.update', 'rust'))->assertNoContent();

    expect(DB::table('guild_preferences')->count())->toBe(0);
    Event::assertNotDispatched(UsersUpdated::class);
});

it('hides the endpoint without the guilds feature, and for a missing guild', function (): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-guild-read.update', 'missing'))->assertNotFound();

    resolve(Features::class)->update(Feature::Guilds, false, adminOnly: false);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-read.update', 'rust'))->assertNotFound();
});

it('shares the 120 reads a minute with the web', function (): void {
    foreach (range(1, 120) as $read) {
        $this->actingAs($this->user)->put(route('guild-read.update', $this->guild->id))->assertRedirect();
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-read.update', 'rust'))->assertTooManyRequests();
});

it('requires a signed request', function (): void {
    $this->putJson(route('api-guild-read.update', 'rust'))->assertUnauthorized();
});
