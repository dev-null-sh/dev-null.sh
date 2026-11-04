<?php

declare(strict_types=1);

use App\Contracts\Services\PushNotificationProvider;
use App\Models\PushSubscription;
use App\Services\PushNotificationProviders\NullPushNotificationProvider;
use Illuminate\Support\Facades\Http;

it('is the push notification provider in testing', function (): void {
    expect(resolve(PushNotificationProvider::class))->toBeInstanceOf(NullPushNotificationProvider::class);
});

it('pushes nothing', function (): void {
    Http::fake();
    $provider = new NullPushNotificationProvider();

    expect($provider->publicKey())->toBeNull()
        ->and($provider->send(PushSubscription::factory()->create()))->toBeTrue();

    Http::assertNothingSent();
});
