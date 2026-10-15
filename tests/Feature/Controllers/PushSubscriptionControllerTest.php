<?php

declare(strict_types=1);

use App\Models\User;

it('may subscribe to push notifications', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->post(route('push-subscription.store'), [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
            'content_encoding' => 'aes128gcm',
        ]);

    $response->assertRedirectToRoute('notification.index');

    $subscription = $user->pushSubscriptions()->sole();

    expect($subscription->endpoint)->toBe('https://push.example.com/abc')
        ->and($subscription->public_key)->toBe('public-key')
        ->and($subscription->auth_token)->toBe('auth-token');
});

it('requires a valid subscription', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->post(route('push-subscription.store'), ['endpoint' => 'http://insecure.example.com']);

    $response->assertSessionHasErrors(['endpoint', 'keys.p256dh', 'keys.auth']);

    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('may unsubscribe from push notifications', function (): void {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/abc', 'public-key', 'auth-token');

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->delete(route('push-subscription.destroy'), ['endpoint' => 'https://push.example.com/abc']);

    $response->assertRedirectToRoute('notification.index');

    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('requires authentication', function (string $method): void {
    $response = $this->{$method}(route('push-subscription.store'));

    $response->assertRedirectToRoute('home');
})->with(['post', 'delete']);
