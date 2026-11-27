<?php

declare(strict_types=1);

use App\Actions\UpdateGuildMembership;
use App\Actions\UpdateGuildMute;
use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('mutes a guild once and keeps the membership, as the web does', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    resolve(UpdateGuildMembership::class)->handle($this->user, $rust, true);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();

    expect($rust->muters()->whereKey($this->user->id)->exists())->toBeTrue()
        ->and($rust->members()->whereKey($this->user->id)->exists())->toBeTrue();
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertJsonPath('guild.is_joined', true)
        ->assertJsonPath('guild.is_muted', true);
});

it('unmutes a guild, also one the user never muted', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();
    expect($rust->muters()->whereKey($this->user->id)->exists())->toBeTrue();

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-mute.destroy', 'rust'))->assertNoContent();
    $this->assertDatabaseMissing('guild_preferences', ['user_id' => $this->user->id, 'guild_id' => $rust->id]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-mute.destroy', 'rust'))->assertNoContent();
    $this->assertDatabaseMissing('guild_preferences', ['user_id' => $this->user->id, 'guild_id' => $rust->id]);
});

it('keeps the membership when unmuting', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    resolve(UpdateGuildMembership::class)->handle($this->user, $rust, true);
    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();
    expect($rust->muters()->whereKey($this->user->id)->exists())->toBeTrue();

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-mute.destroy', 'rust'))->assertNoContent();

    expect($rust->muters()->whereKey($this->user->id)->exists())->toBeFalse()
        ->and($rust->members()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('mutes and unmutes a paused guild', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'user_id' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'go'))->assertNoContent();

    expect($go->muters()->whereKey($this->user->id)->exists())->toBeTrue();

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-mute.destroy', 'go'))->assertNoContent();

    expect($go->muters()->whereKey($this->user->id)->exists())->toBeFalse();
});

it('lets admins mute during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    $rust = Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();

    expect($rust->muters()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('does not find the guild while it is unknown or guilds are not available to the user', function (string $method, string $route, bool $muted, bool $adminOnly, string $handle): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    if ($muted) {
        resolve(UpdateGuildMute::class)->handle($this->user, $rust, true);
    }

    resolve(Features::class)->update(Feature::Guilds, true, $adminOnly);

    signedApiRequest($this->keyPair, $method, route($route, $handle))->assertNotFound();

    expect($rust->muters()->whereKey($this->user->id)->exists())->toBe($muted);
})->with([
    'mute' => ['POST', 'api-guild-mute.store', false],
    'unmute' => ['DELETE', 'api-guild-mute.destroy', true],
])->with([
    'unknown guild' => [false, 'go'],
    'admin preview' => [true, 'rust'],
]);

it('requires a signed request', function (string $method, string $route): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->json($method, route($route, 'rust'))->assertUnauthorized();
})->with([
    'mute' => ['POST', 'api-guild-mute.store'],
    'unmute' => ['DELETE', 'api-guild-mute.destroy'],
]);

it('limits how often the user mutes or unmutes guilds', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    foreach (range(1, 15) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertNoContent();
        signedApiRequest($this->keyPair, 'DELETE', route('api-guild-mute.destroy', 'rust'))->assertNoContent();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-guild-mute.store', 'rust'))->assertTooManyRequests();
});
