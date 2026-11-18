<?php

declare(strict_types=1);

use App\Actions\UpdateGuildMembership;
use App\Actions\UpdateGuildMute;
use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('joins a guild once, keeping when the user joined and clearing a mute, as the web does', function (): void {
    $rust = Guild::factory()->create(['handle' => 'rust']);
    resolve(UpdateGuildMute::class)->handle($this->user, $rust, true);

    $this->freezeSecond();
    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();
    $joinedAt = now()->toImmutable();
    $this->travel(1)->minute();
    resolve(UpdateGuildMute::class)->handle($this->user, $rust, true);
    signedApiRequest($this->keyPair, 'POST', route('api-guild-membership.store', 'rust'))->assertNoContent();

    expect(DB::table('guild_preferences')->where('user_id', $this->user->id)->value('joined_at'))->toBe($joinedAt->toDateTimeString())
        ->and($rust->members()->whereKey($this->user->id)->exists())->toBeTrue()
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

it('lists the moderator first then newest joins in pages of fifty, with follows and the moderator id', function (): void {
    $moderator = User::factory()->create();
    $rust = Guild::factory()->create(['handle' => 'rust', 'user_id' => $moderator->id]);
    $older = User::factory()->create();
    $members = User::factory()->count(50)->create();
    $rust->members()->attach($moderator, ['joined_at' => now()->subDays(2)]);
    $rust->members()->attach($older, ['joined_at' => now()->subDay()]);
    $rust->members()->attach($members->modelKeys(), ['joined_at' => now()]);
    $rust->muters()->attach(User::factory()->create(), ['muted_at' => now()]);
    Guild::factory()->create()->members()->attach(User::factory()->create(), ['joined_at' => now()]);
    $this->user->following()->attach($moderator);
    $this->user->followers()->attach($moderator);
    $this->user->followers()->attach($members->sortByDesc('id')->first());

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', 'rust'))
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.*.id', [$moderator->id, ...$members->sortByDesc('id')->take(49)->values()->modelKeys()])
        ->assertJsonPath('moderator_id', $moderator->id)
        ->assertJsonPath('data.0.is_followed', true)
        ->assertJsonPath('data.0.follows_you', true)
        ->assertJsonPath('data.1.is_followed', false)
        ->assertJsonPath('data.1.follows_you', true)
        ->assertJsonPath('data.2.follows_you', false)
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('links.next', route('api-guild-membership.index', ['guild' => 'rust', 'page' => 2]))
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.pivot');

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', ['guild' => 'rust', 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$members->sortBy('id')->first()->id, $older->id])
        ->assertJsonPath('links.next', null);
});

it('lists the members of a paused guild without a moderator, and an empty guild', function (): void {
    $go = Guild::factory()->create(['handle' => 'go', 'user_id' => null]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', 'go'))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonStructure(['moderator_id'])->assertJsonPath('moderator_id', null);

    $go->members()->attach($this->user, ['joined_at' => now()]);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', 'go'))
        ->assertOk()->assertJsonPath('data.*.id', [$this->user->id])->assertJsonStructure(['moderator_id'])->assertJsonPath('moderator_id', null);
});

it('lets admins list members during the admin preview', function (): void {
    config(['app.admins' => [$this->user->handle]]);
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: true);
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', 'rust'))->assertOk();
});

it('does not find the members while the guild is unknown or guilds are not available to the user', function (bool $adminOnly, string $handle): void {
    Guild::factory()->create(['handle' => 'rust']);
    resolve(Features::class)->update(Feature::Guilds, true, $adminOnly);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', $handle))->assertNotFound();
})->with([
    'unknown guild' => [false, 'go'],
    'admin preview' => [true, 'rust'],
]);

it('requires a signed request to list members', function (): void {
    Guild::factory()->create(['handle' => 'rust']);

    $this->getJson(route('api-guild-membership.index', 'rust'))->assertUnauthorized();
});

it('rejects an invalid members page', function (mixed $page): void {
    Guild::factory()->create(['handle' => 'rust']);

    signedApiRequest($this->keyPair, 'GET', route('api-guild-membership.index', ['guild' => 'rust', 'page' => $page]))
        ->assertUnprocessable()->assertJsonValidationErrors('page');
})->with([0, 'two', PHP_INT_MAX]);
