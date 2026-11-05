<?php

declare(strict_types=1);

use App\Actions\CreateMessage;
use App\Contracts\Services\DevicePushProvider;
use App\Contracts\Services\PushNotificationProvider;
use App\Dto\DevicePush;
use App\Enums\ConversationKind;
use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Jobs\SendRemotePush;
use App\Models\ConversationParticipant;
use App\Models\Device;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->message = Message::factory()->create(['body' => 'Private content must never reach a push service']);
    $this->recipient = User::factory()->create();
    $this->participant = ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->recipient->id]);
    $this->subscription = PushSubscription::query()->create(['user_id' => $this->recipient->id, 'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/wp/recipient'), 'endpoint' => 'https://fcm.googleapis.com/wp/recipient']);
});

it('pushes to every eligible browser of the recipient', function (): void {
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'second', 'endpoint' => 'https://fcm.googleapis.com/wp/second']);
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->message->user_id]);
    $this->message->user->pushSubscriptions()->create(['endpoint_hash' => 'sender', 'endpoint' => 'https://fcm.googleapis.com/wp/sender']);
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->twice()
        ->with(Mockery::on(fn (PushSubscription $subscription): bool => $subscription->user_id === $this->recipient->id))->andReturnTrue();

    resolve(SendRemotePush::class, ['messageId' => $this->message->id, 'userId' => $this->recipient->id])->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
});

it('does not load recipients who have no push subscriptions', function (): void {
    $this->subscription->delete();
    $this->mock(PushNotificationProvider::class)->shouldNotReceive('send');
    $loaded = [];
    Event::listen('eloquent.retrieved: '.User::class, function (User $user) use (&$loaded): void {
        $loaded[] = $user->id;
    });

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));

    expect($loaded)->not->toContain($this->recipient->id);
});

it('delivers a ping event', function (): void {
    $this->message->update(['event' => OrgEvent::Ping, 'body' => '']);
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnTrue();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
});

it('rechecks eligibility at delivery time', function (string $change): void {
    match ($change) {
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
        'history' => $this->participant->update(['visible_from_id' => $this->message->id]),
        'muted' => $this->participant->update(['muted_until_at' => now()->addHour()]),
        'dropped' => $this->participant->update(['status' => RemoteStatus::Dropped]),
        'fetched' => $this->participant->update(['status' => RemoteStatus::Fetched]),
        'left' => $this->participant->delete(),
        'disabled' => $this->recipient->update(['remotes_enabled' => false]),
        'blocked' => $this->recipient->blocking()->attach($this->message->user_id),
        'blocked-by' => $this->message->user->blocking()->attach($this->recipient->id),
        'user-muted' => $this->recipient->muting()->attach($this->message->user_id),
        'deleted' => $this->message->delete(),
        'old' => $this->message->update(['created_at' => now()->subMinutes(6)]),
        'event' => $this->message->update(['event' => OrgEvent::Added]),
    };
    $this->mock(PushNotificationProvider::class)->shouldNotReceive('send');

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
})->with(['read', 'history', 'muted', 'dropped', 'fetched', 'left', 'disabled', 'blocked', 'blocked-by', 'user-muted', 'deleted', 'old', 'event']);

it('allows expired mutes and enabled fetched requests', function (): void {
    config(['remotes.requests' => true]);
    $this->participant->update(['muted_until_at' => now(), 'status' => RemoteStatus::Fetched]);
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnTrue();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
});

it('queues delivery once for a retried message and only after commit', function (): void {
    Queue::fake();
    $clientId = Str::uuid()->toString();
    $action = resolve(CreateMessage::class);
    $message = $action->handle($this->message->user, $this->message->conversation, 'new message', $clientId, linkPreview: false);
    $action->handle($this->message->user, $this->message->conversation, 'new message', $clientId, linkPreview: false);
    Queue::assertPushed(SendRemotePush::class, 1);
    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $job): bool => $job->messageId === $message->id && $job->userId === $this->recipient->id && $job->afterCommit === true && $job->delay->equalTo(now()->addSeconds(10)));
});

it('queues each subscribed recipient independently with a bounded delivery timeout', function (): void {
    $other = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $other->id]);
    PushSubscription::factory()->create(['user_id' => $other->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->message->user_id]);
    PushSubscription::factory()->create(['user_id' => $this->message->user_id]);
    $unsubscribed = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $unsubscribed->id]);

    $message = resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);

    $jobs = DB::table('jobs')->get()->map(fn (object $job): array => json_decode($job->payload, true));
    expect($jobs)->toHaveCount(2);
    expect($jobs->pluck('timeout')->all())->toBe([210, 210]);
    expect($jobs->map(fn (array $payload): string => unserialize($payload['data']['command'])->userId)->sort()->values()->all())
        ->toBe(collect([$this->recipient->id, $other->id])->sort()->values()->all());
    expect($jobs->map(fn (array $payload): int => unserialize($payload['data']['command'])->messageId)->all())->toBe([$message->id, $message->id]);
});

it('delivers twenty browsers per queued recipient without one recipient consuming the next job', function (): void {
    $this->recipient->pushSubscriptions()->createMany(collect(range(1, 19))->map(fn (int $i): array => ['endpoint_hash' => 'first-'.$i, 'endpoint' => 'https://fcm.googleapis.com/wp/first-'.$i])->all());
    $other = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $other->id]);
    $other->pushSubscriptions()->createMany(collect(range(1, 20))->map(fn (int $i): array => ['endpoint_hash' => 'other-'.$i, 'endpoint' => 'https://fcm.googleapis.com/wp/other-'.$i])->all());
    $sent = 0;
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->andReturnUsing(function () use (&$sent): bool {
        $sent++;

        return true;
    });

    resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);
    $this->travel(11)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect($sent)->toBe(20);
    $this->assertDatabaseCount('jobs', 1);

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    expect($sent)->toBe(40);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('retries temporary push failures without keeping or reporting the endpoint', function (): void {
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnFalse();
    Queue::fake();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));

    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $retry): bool => $retry->messageId === $this->message->id && $retry->userId === $this->recipient->id
        && $retry->subscriptionIds === [$this->subscription->id] && $retry->tries === 2 && $retry->delay->equalTo(now()->addSeconds(30)));
});

it('retries only failed browsers across serialized queue deliveries', function (): void {
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'second', 'endpoint' => 'https://fcm.googleapis.com/wp/second']);
    $other = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $other->id]);
    $other->pushSubscriptions()->create(['endpoint_hash' => 'other', 'endpoint' => 'https://fcm.googleapis.com/wp/other']);
    $sent = [];
    $recipientFails = true;
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->andReturnUsing(function (PushSubscription $subscription) use (&$sent, &$recipientFails): bool {
        $sent[] = $subscription->endpoint;

        return ! $recipientFails || $subscription->endpoint !== 'https://fcm.googleapis.com/wp/recipient';
    });
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    dispatch(new SendRemotePush($this->message->id, $other->id)->onConnection('database')->onQueue('push-other'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-other', '--once' => true])->assertSuccessful();

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
    expect($sent)->toHaveCount(3);

    $recipientFails = false;
    $other->pushSubscriptions()->create(['endpoint_hash' => 'new', 'endpoint' => 'https://fcm.googleapis.com/wp/new']);
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    expect($sent)->toHaveCount(4)
        ->and(array_count_values($sent)['https://fcm.googleapis.com/wp/recipient'])->toBe(2);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('stops temporary retries within the original three-attempt budget', function (int $previousAttempts): void {
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->times(3 - $previousAttempts)->andReturnFalse();
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    DB::table('jobs')->where('queue', 'push-retries')->update(['attempts' => $previousAttempts]);

    for ($attempt = $previousAttempts; $attempt < 3; $attempt++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
        $this->travel(31)->seconds();
    }

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with([0, 1, 2]);

it('rechecks eligibility and enrollment before a failed browser retry', function (string $change): void {
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnFalse();
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    match ($change) {
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
        'revoked' => $this->subscription->delete(),
        'transferred' => $this->subscription->update(['user_id' => User::factory()->create()->id]),
    };
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with(['read', 'revoked', 'transferred']);

it('shows phones who wrote, never what', function (bool $org, ?OrgEvent $event, string $title, string $body, string $type): void {
    if ($org) {
        $this->message->conversation->update(['kind' => ConversationKind::Org, 'pair' => null, 'name' => 'Core team']);
    }

    $this->message->update(['event' => $event]);
    DB::table('profiles')->where('id', $this->message->user_id)->update(['name' => 'Alice']);
    $phone = Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnTrue();
    $sent = [];
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturnUsing(function (Device $device, DevicePush $push) use (&$sent): bool {
        $sent[] = [$device->id, $push];

        return true;
    });

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));

    expect($sent)->toEqual([[$phone->id, new DevicePush(
        $title, $body, ['type' => $type, 'conversation_id' => $this->message->conversation_id], $this->message->conversation_id,
    )]]);
})->with([
    'a direct message' => [false, null, 'Alice', 'Sent you a message', 'message'],
    'a direct ping' => [false, OrgEvent::Ping, 'Alice', 'Pinged you', 'ping'],
    'an org message' => [true, null, 'Core team', 'Alice sent a message', 'message'],
    'an org ping' => [true, OrgEvent::Ping, 'Core team', 'Alice pinged everyone', 'ping'],
]);

it('names the sender by handle when they have no name', function (): void {
    DB::table('profiles')->where('id', $this->message->user_id)->update(['name' => '']);
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()
        ->with(Mockery::any(), Mockery::on(fn (DevicePush $push): bool => $push->title === '@'.$this->message->user->profile->handle))->andReturnTrue();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
});

it("leaves phones alone when the recipient switched off the conversation's kind, and still pushes browsers", function (bool $org): void {
    if ($org) {
        $this->message->conversation->update(['kind' => ConversationKind::Org, 'pair' => null, 'name' => 'Core team']);
    }

    $this->recipient->update([$org ? 'push_org_remotes' : 'push_direct_remotes' => false, $org ? 'push_direct_remotes' : 'push_org_remotes' => true]);
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnTrue();
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
})->with(['direct' => false, 'org' => true]);

it('queues a recipient who has only a phone to push to', function (): void {
    Queue::fake();
    $this->subscription->delete();
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $unreachable = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $unreachable->id]);
    Device::factory()->for($unreachable)->create();

    $message = resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);

    Queue::assertPushed(SendRemotePush::class, 1);
    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $job): bool => $job->messageId === $message->id && $job->userId === $this->recipient->id);
});

it('pushes nothing to phones that it would not push to browsers', function (string $change): void {
    $this->subscription->delete();
    Device::factory()->for($this->recipient)->withPushToken()->create();
    match ($change) {
        'muted' => $this->participant->update(['muted_until_at' => now()->addHour()]),
        'blocked' => $this->recipient->blocking()->attach($this->message->user_id),
        'blocked-by' => $this->message->user->blocking()->attach($this->recipient->id),
        'user-muted' => $this->recipient->muting()->attach($this->message->user_id),
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
    };
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
})->with(['muted', 'blocked', 'blocked-by', 'user-muted', 'read']);

it('pushes a stashed conversation like any other, since a new message brings it back', function (): void {
    $this->participant->update(['archived_at' => now()]);
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturnTrue();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));
});

it('retries only the phones and browsers that failed', function (): void {
    $phone = Device::factory()->for($this->recipient)->withPushToken()->create();
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnTrue();
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->twice()->andReturnUsing(fn (Device $device): bool => $device->id !== $phone->id);
    Queue::fake();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class), resolve(DevicePushProvider::class));

    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $retry): bool => $retry->subscriptionIds === [] && $retry->deviceIds === [$phone->id] && $retry->tries === 2);
});
