<?php

declare(strict_types=1);

use App\Models\PushSubscription;
use App\Services\PushNotificationProviders\WebPushNotificationProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\VAPID;

beforeEach(function (): void {
    $this->keys = VAPID::createVapidKeys();
    config(['services.web_push.public_key' => $this->keys['publicKey'], 'services.web_push.private_key' => $this->keys['privateKey']]);
});

it('hands browsers the public key', function (): void {
    expect(new WebPushNotificationProvider()->publicKey())->toBe($this->keys['publicKey']);
});

it('sends an authenticated empty push without message data', function (): void {
    Http::fake(['https://fcm.googleapis.com/*' => Http::response('', 201)]);
    $subscription = PushSubscription::factory()->create();

    expect(new WebPushNotificationProvider()->send($subscription))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === $subscription->endpoint
        && $request->body() === ''
        && str_starts_with($request->header('Authorization')[0], 'vapid t=')
        && $request->header('TTL') === ['300']
        && $request->header('Topic') === ['devnull-remotes']
        && $request->header('Urgency') === ['normal']);
});

it('uses Windows raw push headers for Microsoft Edge endpoints', function (): void {
    Http::fake(['https://wns2-example.notify.windows.com/*' => Http::response('', 201)]);
    $subscription = PushSubscription::factory()->create(['endpoint' => 'https://wns2-example.notify.windows.com/w/?token=example', 'endpoint_hash' => 'edge']);

    new WebPushNotificationProvider()->send($subscription);

    Http::assertSent(fn (Request $request): bool => $request->header('X-WNS-Type') === ['wns/raw']
        && $request->header('Content-Type') === ['application/octet-stream']
        && $request->header('Urgency') === []
        && $request->body() === '');
});

it('forgets subscriptions the browser no longer has, and asks to retry temporary failures', function (int $status, bool $removed, bool $sent): void {
    Http::fake(['https://fcm.googleapis.com/*' => Http::response('', $status)]);
    $subscription = PushSubscription::factory()->create();

    expect(new WebPushNotificationProvider()->send($subscription))->toBe($sent)
        ->and(PushSubscription::query()->whereKey($subscription->id)->exists())->toBe(! $removed);
})->with([
    [404, true, true],
    [410, true, true],
    [403, false, true],
    [429, false, false],
    [500, false, false],
]);

it('asks to retry when the push service cannot be reached', function (): void {
    Http::fake(['https://fcm.googleapis.com/*' => fn () => throw new ConnectionException('unavailable')]);
    $subscription = PushSubscription::factory()->create();

    expect(new WebPushNotificationProvider()->send($subscription))->toBeFalse();
    $this->assertModelExists($subscription);
});
