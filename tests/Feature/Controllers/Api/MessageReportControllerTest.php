<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create();
});

it('reports a message privately, keeping the first report, and tells moderators', function (): void {
    Event::fake([UsersUpdated::class]);
    config(['app.admins' => ['moderator-demo']]);
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $message = sendChatMessage($this->nuno, $this->user, 'buy my course');

    signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $message), ['reason' => 'spam', 'details' => 'Unsolicited ad.'])
        ->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $message), ['reason' => 'other'])
        ->assertNoContent();

    $report = MessageReport::query()->sole();

    expect($report->reporter_id)->toBe($this->user->id)
        ->and($report->message_id)->toBe($message->id)
        ->and($report->reason)->toBe('spam')
        ->and($report->details)->toBe('Unsolicited ad.');

    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation
        && $event->userIds === [$admin->id]
        && $event->data === ['message_reports' => [$report->id]]);
});

it('refuses a missing or unknown reason, or details over 500 characters', function (array $data, string $field): void {
    $message = sendChatMessage($this->nuno, $this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $message), $data)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);

    expect(MessageReport::query()->count())->toBe(0);
})->with([
    'no reason' => [[], 'reason'],
    'unknown reason' => [['reason' => 'approve'], 'reason'],
    'details too long' => [['reason' => 'spam', 'details' => Str::repeat('x', 501)], 'details'],
]);

it('may not report their own message, with a code', function (): void {
    $message = sendChatMessage($this->user, $this->nuno);

    signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $message), ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'You cannot report your own message.', 'code' => 'own_message']);

    expect(MessageReport::query()->count())->toBe(0);
});

it('answers a message the user cannot see, an org event, or a missing message as not found', function (): void {
    $hidden = sendChatMessage($this->nuno, User::factory()->withRemotes()->create());
    $event = Message::factory()->create([
        'conversation_id' => sendChatMessage($this->nuno, $this->user)->conversation_id,
        'user_id' => $this->nuno->id,
        'event' => OrgEvent::cases()[0],
    ]);

    $missing = signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $hidden->id + 1000), ['reason' => 'spam'])
        ->assertNotFound()
        ->json('message');

    foreach ([$hidden, $event] as $message) {
        expect(signedApiRequest($this->keyPair, 'POST', route('api-message-report.store', $message), ['reason' => 'spam'])
            ->assertNotFound()
            ->json('message'))->toBe($missing);
    }

    expect(MessageReport::query()->count())->toBe(0);
});
