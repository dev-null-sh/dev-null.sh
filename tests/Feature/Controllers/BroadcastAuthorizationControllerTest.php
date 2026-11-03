<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'local-key',
            'secret' => 'local-secret',
            'app_id' => 'local-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ],
    ]);
    Broadcast::forgetDrivers();
});

it('signs the user into their own private channel', function (): void {
    $user = User::factory()->create();
    $channel = 'private-users.'.$user->id;

    $this->actingAs($user)
        ->postJson(route('broadcast-authorization.store'), ['socket_id' => '123.456', 'channel_name' => $channel])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['auth' => 'local-key:'.hash_hmac('sha256', '123.456:'.$channel, 'local-secret')]);
});

it("refuses anyone else's channel, and channels that are not a user's", function (string $channel): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('broadcast-authorization.store'), ['socket_id' => '123.456', 'channel_name' => str_replace('{other}', $other->id, $channel)])
        ->assertForbidden();
})->with([
    'another user' => 'private-users.{other}',
    'unprefixed' => 'users.{other}',
    'presence' => 'presence-users.{other}',
    'commit' => 'private-commits.{other}',
]);

it('requires a signed-in user and a valid socket', function (): void {
    $user = User::factory()->create();

    $this->postJson(route('broadcast-authorization.store'), ['socket_id' => '123.456', 'channel_name' => 'private-users.'.$user->id])
        ->assertUnauthorized();

    $this->actingAs($user)
        ->postJson(route('broadcast-authorization.store'), ['socket_id' => 'nope', 'channel_name' => 'private-users.'.$user->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('socket_id');
});

it("signs an approved device into its user's private channel through the API", function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $key = apiKeyPairFor($user);
    $channel = 'private-users.'.$user->id;

    signedApiRequest($key, 'POST', route('api-broadcast-authorization.store'), ['socket_id' => '7.8', 'channel_name' => $channel])
        ->assertOk()
        ->assertExactJson(['auth' => 'local-key:'.hash_hmac('sha256', '7.8:'.$channel, 'local-secret')]);

    signedApiRequest($key, 'POST', route('api-broadcast-authorization.store'), ['socket_id' => '7.8', 'channel_name' => 'private-users.'.$other->id])
        ->assertForbidden();
});
