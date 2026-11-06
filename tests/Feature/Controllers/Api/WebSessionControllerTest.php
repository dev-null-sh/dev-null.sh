<?php

declare(strict_types=1);

use App\Dto\MobileAuthorization;
use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = SshKeyPair::generate();
    $this->device = Device::factory()->withPublicKey($this->keyPair->publicKey)->create([
        'user_id' => $this->user->id,
        'app_id' => MobileAuthorization::LiteMobileAppId,
    ]);
});

it('signs in a web session for the device that signs the request', function (): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))
        ->assertOk()
        ->assertExactJson(['onboarded' => true])
        ->assertCookie(config('session.cookie'));

    $guard = Auth::guard('web');
    assert($guard instanceof SessionGuard);

    expect(session($guard->getName()))->toBe($this->user->id)
        ->and(session(Device::SessionKey))->toBe($this->device->id)
        ->and(collect($response->headers->getCookies())->map->getName()->filter(fn (string $name): bool => str_starts_with($name, 'remember_')))->toBeEmpty();
});

it('tells the app when the user has yet to onboard', function (): void {
    $this->user->update(['onboarded_at' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))
        ->assertOk()
        ->assertExactJson(['onboarded' => false]);
});

it('does not count as seeing the user, as the page loaded next does', function (): void {
    $this->user->update(['last_seen_at' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))->assertOk();

    expect($this->user->refresh()->last_seen_at)->toBeNull();

    $this->assertDatabaseMissing('user_active_days', ['user_id' => $this->user->id]);
});

it('refuses a device of any other app', function (?string $appId): void {
    $this->device->update(['app_id' => $appId]);

    signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))->assertForbidden();

    expect(session(Device::SessionKey))->toBeNull();
})->with(['another app' => 'ios:com.nativephp.devnull', 'no app id' => null]);

it('refuses a key that is not approved', function (): void {
    $this->device->delete();

    signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))->assertUnauthorized();

    $this->assertGuest('web');
});

it('refuses a request without a signature', function (): void {
    $this->postJson(route('api-web-session.store'))->assertUnauthorized();

    $this->assertGuest('web');
});

it('refuses a banned user', function (): void {
    $banned = User::factory()->banned()->create();
    $this->device->update(['user_id' => $banned->id]);

    signedApiRequest($this->keyPair, 'POST', route('api-web-session.store'))->assertForbidden();

    $this->assertGuest('web');
});
