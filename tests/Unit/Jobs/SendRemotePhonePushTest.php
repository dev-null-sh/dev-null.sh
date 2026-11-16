<?php

declare(strict_types=1);

use App\Actions\CreateMessage;
use App\Contracts\Services\DevicePushProvider;
use App\Contracts\Services\PushNotificationProvider;
use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Enums\ConversationKind;
use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Exceptions\DevicePushCredentialsRejected;
use App\Jobs\SendRemotePhonePush;
use App\Jobs\SendRemotePush;
use App\Models\ConversationParticipant;
use App\Models\Device;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->message = Message::factory()->create(['body' => 'Private content must never reach a push service']);
    $this->recipient = User::factory()->create();
    $this->participant = ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->recipient->id]);
    $this->subscription = PushSubscription::query()->create(['user_id' => $this->recipient->id, 'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/wp/recipient'), 'endpoint' => 'https://fcm.googleapis.com/wp/recipient']);
    $this->device = Device::factory()->for($this->recipient)->withPushToken()->create();
});

it('queues a recipient who has only a phone to push to', function (): void {
    Queue::fake();
    $this->subscription->delete();
    $unreachable = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $unreachable->id]);
    Device::factory()->for($unreachable)->create();

    $message = resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);

    Queue::assertPushed(SendRemotePhonePush::class, 1);
    Queue::assertNotPushed(SendRemotePush::class);
    Queue::assertPushed(SendRemotePhonePush::class, fn (SendRemotePhonePush $job): bool => $job->messageId === $message->id && $job->userId === $this->recipient->id
        && $job->afterCommit === true && $job->delay->equalTo(now()->addSeconds(10)));
});

it('queues browsers and phones as jobs of their own', function (): void {
    Queue::fake();

    resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);

    Queue::assertPushed(SendRemotePush::class, 1);
    Queue::assertPushed(SendRemotePhonePush::class, 1);
});

it('loads browser and phone reachability in bulk for an org', function (): void {
    $this->message->conversation->update(['kind' => ConversationKind::Org, 'pair' => null]);
    foreach (User::factory()->count(8)->create() as $user) {
        ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $user->id]);
        PushSubscription::factory()->create(['user_id' => $user->id]);
        Device::factory()->for($user)->withPushToken()->create();
    }

    Queue::fake();
    DB::enableQueryLog();
    DB::flushQueryLog();

    SendRemotePush::dispatchFor($this->message);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(count($queries))->toBeLessThanOrEqual(3);
    Queue::assertPushed(SendRemotePush::class, 9);
    Queue::assertPushed(SendRemotePhonePush::class, 9);
});

it('shows phones who wrote, never what', function (bool $org, ?OrgEvent $event, string $title, string $body, string $type): void {
    if ($org) {
        $this->message->conversation->update(['kind' => ConversationKind::Org, 'pair' => null, 'name' => $title === 'Org' ? null : 'Core team']);
    }

    $this->message->update(['event' => $event]);
    DB::table('profiles')->where('id', $this->message->user_id)->update(['name' => 'Alice']);
    $sent = [];
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturnUsing(function (Device $device, DevicePush $push) use (&$sent): DevicePushResult {
        $sent[] = [$device->id, $push];

        return DevicePushResult::settled();
    });

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    $conversationId = $this->message->conversation_id;
    $data = ['type' => $type, 'conversation_id' => $conversationId, 'kind' => $org ? 'org' : 'direct', ...$org ? [] : ['handle' => $this->message->user->profile->handle]];
    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe($this->device->id)
        ->and($sent[0][1])->title->toBe($title)->body->toBe($body)->group->toBe($conversationId)
        ->and($sent[0][1]->data)->toBe([...$data, 'url' => 'devnull://push?'.http_build_query($data, encoding_type: PHP_QUERY_RFC3986)]);
})->with([
    'a direct message' => [false, null, 'Alice', 'Sent you a message', 'message'],
    'a direct ping' => [false, OrgEvent::Ping, 'Alice', 'Pinged you', 'ping'],
    'an org message' => [true, null, 'Core team', 'Alice sent a message', 'message'],
    'an org ping' => [true, OrgEvent::Ping, 'Core team', 'Alice pinged everyone', 'ping'],
    'an unnamed org message' => [true, null, 'Org', 'Alice sent a message', 'message'],
    'an unnamed org ping' => [true, OrgEvent::Ping, 'Org', 'Alice pinged everyone', 'ping'],
]);

it('names the sender by handle when they have no name', function (): void {
    DB::table('profiles')->where('id', $this->message->user_id)->update(['name' => '']);
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()
        ->with(Mockery::any(), Mockery::on(fn (DevicePush $push): bool => $push->title === '@'.$this->message->user->profile->handle))->andReturn(DevicePushResult::settled());

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));
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
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));
})->with(['read', 'history', 'muted', 'dropped', 'fetched', 'left', 'disabled', 'blocked', 'blocked-by', 'user-muted', 'deleted', 'old', 'event']);

it('pushes nothing to phones that it would not push to browsers', function (string $change): void {
    match ($change) {
        'muted' => $this->participant->update(['muted_until_at' => now()->addHour()]),
        'blocked' => $this->recipient->blocking()->attach($this->message->user_id),
        'blocked-by' => $this->message->user->blocking()->attach($this->recipient->id),
        'user-muted' => $this->recipient->muting()->attach($this->message->user_id),
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
    };
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));
})->with(['muted', 'blocked', 'blocked-by', 'user-muted', 'read']);

it("leaves phones alone when the recipient switched off the conversation's kind", function (bool $org): void {
    if ($org) {
        $this->message->conversation->update(['kind' => ConversationKind::Org, 'pair' => null, 'name' => 'Core team']);
    }

    $this->recipient->update([$org ? 'push_org_remotes' : 'push_direct_remotes' => false, $org ? 'push_direct_remotes' : 'push_org_remotes' => true]);
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));
})->with(['direct' => false, 'org' => true]);

it('pushes a stashed conversation like any other, since a new message brings it back', function (): void {
    $this->participant->update(['archived_at' => now()]);
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturn(DevicePushResult::settled());

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));
});

it('retries only the phones that failed, within the original budget', function (): void {
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->twice()
        ->andReturnUsing(fn (Device $device): DevicePushResult => $device->id === $this->device->id ? DevicePushResult::retry() : DevicePushResult::settled());
    Queue::fake();

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    Queue::assertPushed(SendRemotePhonePush::class, fn (SendRemotePhonePush $retry): bool => $retry->messageId === $this->message->id
        && $retry->userId === $this->recipient->id && $retry->deviceIds === [$this->device->id] && $retry->tries === 2
        && $retry->delay->equalTo(now()->addSeconds(30)));
});

it('waits as long as Google asks before retrying a phone', function (): void {
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturn(DevicePushResult::retry(120));
    Queue::fake();

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    Queue::assertPushed(SendRemotePhonePush::class, fn (SendRemotePhonePush $retry): bool => $retry->delay->equalTo(now()->addSeconds(120)));
});

it('gives browser retries their own wait when a phone asks for a longer one', function (): void {
    $this->mock(PushNotificationProvider::class)->shouldReceive('send')->once()->andReturnFalse();
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturn(DevicePushResult::retry(3600));
    Queue::fake();

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(PushNotificationProvider::class));
    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $retry): bool => $retry->delay->equalTo(now()->addSeconds(30)));
    Queue::assertPushed(SendRemotePhonePush::class, fn (SendRemotePhonePush $retry): bool => $retry->delay->equalTo(now()->addSeconds(3600)));
});

it('preserves failed phones and continues delivery after an unexpected device failure', function (): void {
    Device::factory()->for($this->recipient)->withPushToken()->count(2)->create();
    Exceptions::fake();
    Queue::fake();
    $sent = [];
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->times(3)->andReturnUsing(function (Device $device) use (&$sent): DevicePushResult {
        $sent[] = $device->id;

        throw_if(count($sent) === 2, RuntimeException::class, 'Temporary cache failure');

        return count($sent) === 1 ? DevicePushResult::retry() : DevicePushResult::settled();
    });

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    Exceptions::assertReportedCount(1);
    Queue::assertPushed(SendRemotePhonePush::class, fn (SendRemotePhonePush $retry): bool => $retry->deviceIds === array_slice($sent, 0, 2) && $retry->tries === 2);
    Queue::assertPushed(SendRemotePhonePush::class, 1);
});

it('stops all phone retries when credentials are permanently refused', function (): void {
    Device::factory()->for($this->recipient)->withPushToken()->count(2)->create();
    Queue::fake();
    $calls = 0;
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->twice()->andReturnUsing(function () use (&$calls): DevicePushResult {
        throw_if(++$calls === 2, DevicePushCredentialsRejected::class, 'FCM OAuth request failed (HTTP 400).');

        return DevicePushResult::retry();
    });

    new SendRemotePhonePush($this->message->id, $this->recipient->id)->handle(resolve(DevicePushProvider::class));

    Queue::assertNothingPushed();
});

it('splits phone delivery into bounded jobs without consuming the retry budget', function (): void {
    Device::factory()->for($this->recipient)->withPushToken()->count(11)->create();
    $sent = [];
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->times(12)->andReturnUsing(function (Device $device) use (&$sent): DevicePushResult {
        $sent[] = $device->id;

        return DevicePushResult::settled();
    });
    Queue::fake();

    new SendRemotePhonePush($this->message->id, $this->recipient->id, tries: 2)->onConnection('database')->onQueue('phones')->handle(resolve(DevicePushProvider::class));

    expect($sent)->toHaveCount(5);
    Queue::assertPushed(SendRemotePhonePush::class, 2);
    foreach (Queue::pushed(SendRemotePhonePush::class) as $batch) {
        expect(count($batch->deviceIds))->toBeLessThanOrEqual(5);
        expect($batch->tries)->toBe(2)
            ->and($batch->connection)->toBe('database')
            ->and($batch->queue)->toBe('phones');
        $batch->handle(resolve(DevicePushProvider::class));
    }

    expect($sent)->toHaveCount(12)->toBe(Device::query()->where('user_id', $this->recipient->id)->orderBy('id')->pluck('id')->all());
});

it('stops temporary retries within the original three-attempt budget', function (int $previousAttempts): void {
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->times(3 - $previousAttempts)->andReturn(DevicePushResult::retry());
    dispatch(new SendRemotePhonePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    DB::table('jobs')->where('queue', 'push-retries')->update(['attempts' => $previousAttempts]);

    for ($attempt = $previousAttempts; $attempt < 3; $attempt++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
        $this->travel(121)->seconds();
    }

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with([0, 1, 2]);

it('rechecks eligibility before a failed phone retry', function (string $change): void {
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->once()->andReturn(DevicePushResult::retry());
    dispatch(new SendRemotePhonePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    match ($change) {
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
        'revoked' => $this->device->delete(),
        'transferred' => $this->device->update(['user_id' => User::factory()->create()->id]),
    };
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with(['read', 'revoked', 'transferred']);
