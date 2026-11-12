<?php

declare(strict_types=1);

use App\Contracts\Services\PushNotificationProvider;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('requires a signed-in account', function (): void {
    $this->getJson(route('push-subscription.index'))->assertUnauthorized();
    $this->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/example'])->assertUnauthorized();
    $this->deleteJson(route('push-subscription.destroy'), ['endpoint' => 'https://fcm.googleapis.com/wp/example'])->assertUnauthorized();
});

it('exposes only the public key browsers subscribe with', function (): void {
    $this->mock(PushNotificationProvider::class)->shouldReceive('publicKey')->andReturn('public');

    $this->actingAs(User::factory()->create())->getJson(route('push-subscription.index'))->assertExactJson(['public_key' => 'public']);
});

it('stores an encrypted endpoint and transfers the browser to the current account without duplicating it', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/wp/browser-one';
    $this->actingAs($first)->withCredentials()->withCookie(config('session.cookie'), Str::random(40))
        ->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $first->id])->assertNoContent();
    $subscription = PushSubscription::query()->sole();
    expect($subscription->endpoint)->toBe($endpoint)
        ->and(DB::table('push_subscriptions')->value('endpoint'))->not->toContain($endpoint)
        ->and($subscription->toArray())->toHaveKeys(['id'])->not->toHaveKeys(['endpoint', 'endpoint_hash', 'user_id']);
    expect($first->load('pushSubscriptions')->toArray())->not->toHaveKey('push_subscriptions');
    $this->actingAs($second)->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $second->id])->assertNoContent();
    expect(PushSubscription::query()->count())->toBe(1)->and($subscription->refresh()->user_id)->toBe($second->id);
    $this->actingAs($first)->deleteJson(route('push-subscription.destroy'), ['endpoint' => $endpoint, 'user_id' => $first->id])->assertNoContent();
    $this->assertModelExists($subscription);
    $this->actingAs($second)->deleteJson(route('push-subscription.destroy'), ['endpoint' => $endpoint, 'user_id' => $second->id])->assertNoContent();
    $this->assertModelMissing($subscription);
});

it('limits new enrollments to twenty without replacing existing browsers', function (): void {
    $user = User::factory()->create();
    PushSubscription::factory()->count(19)->create(['user_id' => $user->id]);
    $endpoint = 'https://fcm.googleapis.com/wp/twentieth';
    $this->actingAs($user)->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $user->id])->assertNoContent();
    $ids = $user->pushSubscriptions()->pluck('id')->sort()->values()->all();
    expect($ids)->toHaveCount(20);

    $newEndpoint = 'https://fcm.googleapis.com/wp/twenty-first';
    $this->postJson(route('push-subscription.store'), ['endpoint' => $newEndpoint, 'user_id' => $user->id])
        ->assertUnprocessable()->assertJsonPath('errors.endpoint.0', 'You have off-site notifications enabled in 20 browsers or apps. Turn them off in one before enabling another.');
    expect($user->pushSubscriptions()->pluck('id')->sort()->values()->all())->toBe($ids);

    $this->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $user->id])->assertNoContent();
    expect($user->pushSubscriptions()->count())->toBe(20);
    $this->deleteJson(route('push-subscription.destroy'), ['endpoint' => $endpoint, 'user_id' => $user->id])->assertNoContent();
    $this->postJson(route('push-subscription.store'), ['endpoint' => $newEndpoint, 'user_id' => $user->id])->assertNoContent();
    expect($user->pushSubscriptions()->count())->toBe(20);
});

it('applies the limit to the current account without transferring another accounts subscription on rejection', function (): void {
    $full = User::factory()->create();
    PushSubscription::factory()->count(20)->create(['user_id' => $full->id]);
    $other = PushSubscription::factory()->create();
    $this->actingAs($full)->postJson(route('push-subscription.store'), ['endpoint' => $other->endpoint, 'user_id' => $full->id])
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    expect($other->refresh()->user_id)->not->toBe($full->id);
    $this->actingAs(User::query()->findOrFail($other->user_id))->postJson(route('push-subscription.store'), ['endpoint' => $other->endpoint, 'user_id' => $other->user_id])->assertNoContent();
});

it('accepts supported browser endpoints', function (string $endpoint): void {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $user->id])->assertNoContent();
})->with(['https://fcm.googleapis.com/wp/example', 'https://updates.push.services.mozilla.com/wpush/v2/example', 'https://web.push.apple.com/Qexample', 'https://wns2-example.notify.windows.com/w/?token=example']);

it('rejects arbitrary destinations and malformed endpoints', function (mixed $endpoint): void {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('push-subscription.store'), ['endpoint' => $endpoint, 'user_id' => $user->id])
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
})->with([null, 4, [['invalid']], '', 'http://fcm.googleapis.com/wp/a', 'https://127.0.0.1/push', 'https://notify.windows.com.evil.test/w/', 'https://evilnotify.windows.com/w/', 'https://fcm.googleapis.com.attacker.test/a', 'https://name@fcm.googleapis.com/wp/a', 'https://fcm.googleapis.com:444/wp/a', 'https://fcm.googleapis.com/wp/a#secret', 'https://fcm.googleapis.com/', 'https://fcm.googleapis.com', 'https://[', str_repeat('a', 2049)]);

it('validates deletion and refuses subscriptions while remotes are off', function (): void {
    $user = User::factory()->create(['remotes_enabled' => false]);
    $this->actingAs($user);
    $this->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/a', 'user_id' => $user->id])->assertForbidden();
    $this->deleteJson(route('push-subscription.destroy'), ['user_id' => $user->id])->assertUnprocessable()->assertJsonValidationErrors('endpoint');
});

it('uses current remotes consent when the authenticated user was loaded before a settings change', function (bool $enabled): void {
    $user = User::factory()->create(['remotes_enabled' => ! $enabled]);
    $this->actingAs($user)->withCredentials()->withCookie(config('session.cookie'), Str::random(40));
    User::query()->whereKey($user->id)->update(['remotes_enabled' => $enabled]);

    $response = $this->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/current-consent', 'user_id' => $user->id]);

    $response->assertStatus($enabled ? 204 : 403);

    expect($user->pushSubscriptions()->count())->toBe($enabled ? 1 : 0);
})->with([true, false]);

it('removes only this browser on sign-out and all browsers when remotes are disabled', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/other', 'user_id' => $user->id])->assertNoContent();
    $this->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/current', 'user_id' => $user->id])->assertNoContent();
    $this->post(route('logout'))->assertRedirect();
    expect($user->pushSubscriptions()->count())->toBe(1)->and($user->pushSubscriptions()->sole()->endpoint)->toEndWith('/other');
    $this->actingAs($user)->putJson(route('user-remotes.update'), ['enabled' => false])->assertRedirect();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('rejects push mutations from another account or missing consent identity', function (string $method, bool $missing): void {
    $initiator = User::factory()->create();
    $current = User::factory()->create();
    $subscription = PushSubscription::factory()->create(['user_id' => $current->id]);
    $payload = ['endpoint' => $subscription->endpoint, ...$missing ? [] : ['user_id' => $initiator->id]];

    $this->actingAs($current)->{$method}(route($method === 'postJson' ? 'push-subscription.store' : 'push-subscription.destroy'), $payload)
        ->assertForbidden()->assertJsonPath('message', 'Your signed-in account changed.');

    expect($subscription->refresh()->user_id)->toBe($current->id);
    $this->assertDatabaseCount('push_subscriptions', 1);
})->with(['postJson', 'deleteJson'])->with([true, false]);

it('reports a changed enrollment account before checking its Remotes availability', function (): void {
    $initiator = User::factory()->create();
    $current = User::factory()->create(['remotes_enabled' => false]);

    $this->actingAs($current)->postJson(route('push-subscription.store'), ['endpoint' => 'https://fcm.googleapis.com/wp/account-switch', 'user_id' => $initiator->id])
        ->assertForbidden()->assertJsonPath('message', 'Your signed-in account changed.');

    $this->assertDatabaseCount('push_subscriptions', 0);
});
