<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('shows every kind until the user chooses', function (): void {
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-user-settings.show'))
        ->assertOk()
        ->assertJsonPath('data.notification_types', 'all');
});

it('saves the kinds the user wants to see, and answers with them and the unread count of only those', function (string $type, int $expected): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    Notification::factory()->for($user, 'recipient')->replied(Commit::factory()->for($user)->create())->create();
    Notification::factory()->for($user, 'recipient')->starred()->create();
    Notification::factory()->for($user, 'recipient')->create();

    signedApiRequest($keyPair, 'PUT', route('api-user-notification-types.update'), ['type' => $type])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => ['type' => $type], 'unread_notifications_count' => $expected]);

    expect($user->refresh()->notification_types)->toBe($type)
        ->and(signedApiRequest($keyPair, 'GET', route('api-user-settings.show'))->json('data.notification_types'))->toBe($type);
})->with([
    'all' => ['all', 3],
    'one kind' => ['replies', 1],
    'several kinds' => ['replies,stars', 2],
    'none' => ['none', 0],
]);

it('counts only the chosen kinds everywhere the API answers with the unread count, and lists them by default', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $reply = Notification::factory()->for($user, 'recipient')->replied(Commit::factory()->for($user)->create())->create();
    Notification::factory()->for($user, 'recipient')->create();

    signedApiRequest($keyPair, 'PUT', route('api-user-notification-types.update'), ['type' => 'replies'])->assertOk();

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->assertJsonPath('unread_notifications_count', 1);
    signedApiRequest($keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => 'Europe/Lisbon'])->assertJsonPath('unread_notifications_count', 1);
    signedApiRequest($keyPair, 'GET', route('api-notification.index'))
        ->assertJsonPath('filters.type', 'replies')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reply->id);
});

it('saves the kinds the list was asked for, as the web does', function (): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-notification.index', ['type' => 'follows,mentions']))
        ->assertOk()
        ->assertJsonPath('filters.type', 'follows,mentions');

    expect($user->refresh()->notification_types)->toBe('follows,mentions');
});

it('needs kinds the notifications list knows', function (mixed $type): void {
    $user = User::factory()->create(['notification_types' => 'replies']);

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-notification-types.update'), $type === null ? [] : ['type' => $type])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    expect($user->refresh()->notification_types)->toBe('replies');
})->with([
    'missing' => [null],
    'empty' => [''],
    'unknown kind' => ['replies,secret'],
    'not a string' => [['replies']],
]);

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-notification-types.update'), ['type' => 'none'])->assertUnauthorized();
});
