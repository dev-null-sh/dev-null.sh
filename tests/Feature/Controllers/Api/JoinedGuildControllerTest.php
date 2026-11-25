<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);
    $this->travelTo('2026-11-18 12:00:00');

    $this->user = User::factory()->create();
    $this->author = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

function joinApiGuild(User $user, Guild $guild, string $joinedAt, ?string $readAt = null, ?string $mutedAt = null): void
{
    DB::table('guild_preferences')->insert(['user_id' => $user->id, 'guild_id' => $guild->id, 'joined_at' => $joinedAt, 'read_at' => $readAt, 'muted_at' => $mutedAt]);
}

it('lists the joined guilds as the left navigation on the web does, the most missed commits first', function (): void {
    $quiet = Guild::factory()->create(['handle' => 'aardvark', 'name' => 'Aardvark', 'emoji' => '🐜']);
    $busy = Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust', 'emoji' => '🦀', 'icon_path' => 'guild-icons/rust.webp']);
    joinApiGuild($this->user, $quiet, '2026-11-01 00:00:00');
    joinApiGuild($this->user, $busy, '2026-11-01 00:00:00', '2026-11-18 10:00:00');
    joinApiGuild($this->user, Guild::factory()->create(['handle' => 'muted']), '2026-11-01 00:00:00', mutedAt: '2026-11-02 00:00:00');
    Guild::factory()->create(['handle' => 'unjoined']);

    Commit::factory()->for($this->author)->create(['guild_id' => $busy->id, 'created_at' => '2026-11-18 09:00:00']);
    Commit::factory()->for($this->author)->count(2)->create(['guild_id' => $busy->id, 'created_at' => '2026-11-18 11:00:00']);

    signedApiRequest($this->keyPair, 'GET', route('api-joined-guild.index'))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['rust', 'aardvark'])
        ->assertJsonPath('data.0', [
            'handle' => 'rust',
            'name' => 'Rust',
            'emoji' => '🦀',
            'icon_url' => $busy->apiIconUrl($this->user),
            'missed_commits' => 2,
            'read_from' => '2026-11-18T10:00:00Z',
            'html_url' => route('guild.show', 'rust'),
        ])
        ->assertJsonPath('data.1.missed_commits', 0)
        ->assertJsonPath('data.1.read_from', '2026-11-01T00:00:00Z');
});

it('lists nothing before the user joins a guild', function (): void {
    Guild::factory()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-joined-guild.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('hides the endpoint without the guilds feature', function (): void {
    resolve(Features::class)->update(Feature::Guilds, false, adminOnly: false);

    signedApiRequest($this->keyPair, 'GET', route('api-joined-guild.index'))->assertNotFound();
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-joined-guild.index'))->assertUnauthorized();
});
