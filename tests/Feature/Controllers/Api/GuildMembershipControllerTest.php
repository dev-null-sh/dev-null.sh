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

it('joins a guild once, clearing a mute, as the web does', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    resolve(UpdateGuildMute::class)->handle($this->user, $rust, true);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();

    expect($rust->members()->whereKey($this->user->id)->exists())->toBeTrue()
        ->and($rust->muters()->whereKey($this->user->id)->exists())->toBeFalse();
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))
        ->assertJsonPath('guild.member_count', 1)
        ->assertJsonPath('guild.is_joined', true)
        ->assertJsonPath('guild.is_muted', false);
});

it('leaves a guild, also one the user never joined', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-membership.destroy', 'rust'))->assertNoContent();
    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-membership.destroy', 'rust'))->assertNoContent();

    expect($rust->members()->whereKey($this->user->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('guild_preferences', ['user_id' => $this->user->id, 'guild_id' => $rust->id]);
});

it('keeps a mute when leaving', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();
    resolve(UpdateGuildMute::class)->handle($this->user, $rust, true);

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-membership.destroy', 'rust'))->assertNoContent();

    expect($rust->members()->whereKey($this->user->id)->exists())->toBeFalse()
        ->and($rust->muters()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('joins and leaves a paused guild', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'user_id' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'go'))->assertNoContent();

    expect($go->members()->whereKey($this->user->id)->exists())->toBeTrue();

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-membership.destroy', 'go'))->assertNoContent();

    expect($go->members()->whereKey($this->user->id)->exists())->toBeFalse();
});

it('lets admins join during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    $rust = Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();

    expect($rust->members()->whereKey($this->user->id)->exists())->toBeTrue();
});

it('does not find the guild while it is unknown or guilds are not available to the user', function (string $method, string $route, bool $joined, bool $adminOnly, string $handle): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    if ($joined) {
        resolve(UpdateGuildMembership::class)->handle($this->user, $rust, true);
    }

    resolve(Features::class)->update(Feature::Guilds, true, $adminOnly);

    signedApiRequest($this->keyPair, $method, route($route, $handle))->assertNotFound();

    expect($rust->members()->whereKey($this->user->id)->exists())->toBe($joined);
})->with([
    'join' => ['POST', 'api-guild-membership.store', false],
    'leave' => ['DELETE', 'api-guild-membership.destroy', true],
])->with([
    'unknown guild' => [false, 'go'],
    'admin preview' => [true, 'rust'],
]);

it('requires a signed request', function (string $method, string $route): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->json($method, route($route, 'rust'))->assertUnauthorized();
})->with([
    'join' => ['POST', 'api-guild-membership.store'],
    'leave' => ['DELETE', 'api-guild-membership.destroy'],
]);

it('limits how often the user joins or leaves guilds', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    foreach (range(1, 15) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();
        signedApiRequest($this->keyPair, 'DELETE', route('api-guild-membership.destroy', 'rust'))->assertNoContent();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertTooManyRequests();
});
