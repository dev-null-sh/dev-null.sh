<?php

declare(strict_types=1);

use App\Actions\CreateFollowNotification;
use App\Actions\CreateMentionNotifications;
use App\Actions\CreateReplyNotifications;
use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Enums\CommitStatus;
use App\Jobs\SendNotificationPush;
use App\Models\Commit;
use App\Models\Device;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->recipient = User::factory()->create();
    $this->phone = Device::factory()->for($this->recipient)->withPushToken()->create();
    $this->actor = User::factory()->create(['handle' => 'alice', 'name' => 'Alice']);
});

/**
 * @return ArrayObject<int, array{0: string, 1: DevicePush}>
 */
function capturePhonePushes(bool $succeeds = true): ArrayObject
{
    $sent = new ArrayObject();
    test()->mock(DevicePushProvider::class)->shouldReceive('send')->andReturnUsing(function (Device $device, DevicePush $push) use ($sent, $succeeds): bool {
        $sent[] = [$device->id, $push];

        return $succeeds;
    });

    return $sent;
}

it('queues a push for a follow, once, only after commit', function (): void {
    Queue::fake();
    $action = resolve(CreateFollowNotification::class);

    $action->handle($this->actor, $this->recipient);
    $action->handle($this->actor, $this->recipient);

    $notification = $this->recipient->notifications()->sole();
    Queue::assertPushed(SendNotificationPush::class, 1);
    Queue::assertPushed(SendNotificationPush::class, fn (SendNotificationPush $job): bool => $job->notificationId === $notification->id
        && $job->afterCommit === true && $job->delay->equalTo(now()->addSeconds(10)));
});

it('queues a push for replies and mentions', function (): void {
    Queue::fake();
    $parent = Commit::factory()->for($this->recipient)->create();
    $reply = Commit::factory()->for($parent, 'parent')->for($this->actor)->create();
    $mentioned = User::factory()->create(['handle' => 'bob']);
    Device::factory()->for($mentioned)->withPushToken()->create();
    $mention = Commit::factory()->for($this->actor)->create(['body' => 'Hi @bob', 'mentions' => ['bob' => $mentioned->id]]);

    resolve(CreateReplyNotifications::class)->handle($reply);
    resolve(CreateMentionNotifications::class)->handle($mention);

    Queue::assertPushed(SendNotificationPush::class, fn (SendNotificationPush $job): bool => $job->notificationId === $this->recipient->notifications()->sole()->id);
    Queue::assertPushed(SendNotificationPush::class, fn (SendNotificationPush $job): bool => $job->notificationId === $mentioned->notifications()->sole()->id);
    Queue::assertPushed(SendNotificationPush::class, 2);
});

it('queues nothing for someone without a phone to push to', function (): void {
    Queue::fake();
    $this->phone->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);

    resolve(CreateFollowNotification::class)->handle($this->actor, $this->recipient);
    SendNotificationPush::dispatchFor([]);

    Queue::assertNothingPushed();
});

it('pushes who did it to every phone of the recipient, and no text they wrote', function (): void {
    $tablet = Device::factory()->for($this->recipient)->withPushToken()->create();
    Device::factory()->for($this->recipient)->create();
    Device::factory()->withPushToken()->create();
    $notification = Notification::factory()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    $sent = capturePhonePushes();

    resolve(SendNotificationPush::class, ['notificationId' => $notification->id])->handle(resolve(DevicePushProvider::class));

    expect(collect($sent)->pluck(0)->sort()->values()->all())->toBe(collect([$this->phone->id, $tablet->id])->sort()->values()->all())
        ->and($sent[0][1])->toEqual(new DevicePush('Alice', 'Followed you', ['type' => 'followed', 'notification_id' => $notification->id, 'handle' => 'alice']))
        ->and($sent[0][1]->data['url'])->toBe("devnull://push?type=followed&notification_id={$notification->id}&handle=alice");
});

it('says what happened for each kind', function (string $kind, bool $forOrganization, string $body): void {
    $organization = Organization::factory()->create(['name' => 'Laravel']);
    $factory = Notification::factory()->{$kind}();
    $notification = $factory->create([
        'recipient_id' => $this->recipient->id,
        'actor_id' => $this->actor->id,
        'organization_id' => $forOrganization ? $organization->id : null,
    ]);
    $sent = capturePhonePushes();

    new SendNotificationPush($notification->id)->handle(resolve(DevicePushProvider::class));

    expect($sent[0][1]->title)->toBe('Alice')
        ->and($sent[0][1]->body)->toBe($body)
        ->and($sent[0][1]->data['commit_id'])->toBe($notification->commit_id);
})->with([
    'a reply' => ['replied', false, 'Replied to your commit'],
    "a reply to an organization's commit" => ['replied', true, "Replied to Laravel's commit"],
    'a mention' => ['mentioned', false, 'Mentioned you'],
    'a mention of an organization' => ['mentioned', true, 'Mentioned Laravel'],
]);

it('names the actor by handle when they have no name', function (): void {
    DB::table('profiles')->where('id', $this->actor->id)->update(['name' => '']);
    $notification = Notification::factory()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    $sent = capturePhonePushes();

    new SendNotificationPush($notification->id)->handle(resolve(DevicePushProvider::class));

    expect($sent[0][1]->title)->toBe('@alice');
});

it('pushes nothing that changed since it was queued', function (string $change): void {
    $notification = $change === 'unfollowed'
        ? Notification::factory()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id])
        : Notification::factory()->mentioned()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    match ($change) {
        'unfollowed' => $this->actor->following()->detach($this->recipient->id),
        'rejected' => $notification->commit->update(['status' => CommitStatus::Rejected]),
        'read' => $notification->update(['read_at' => now()]),
        'deleted' => $notification->delete(),
        'switched off' => $this->recipient->update(['push_mentions' => false]),
        'muted' => $this->recipient->muting()->attach($this->actor->id),
        'blocked' => $this->recipient->blocking()->attach($this->actor->id),
        'blocked by' => $this->actor->blocking()->attach($this->recipient->id),
    };
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendNotificationPush($notification->id)->handle(resolve(DevicePushProvider::class));
})->with(['read', 'deleted', 'unfollowed', 'rejected', 'switched off', 'muted', 'blocked', 'blocked by']);

it('pushes nothing for kinds without a switch', function (): void {
    $notification = Notification::factory()->starred()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    $this->mock(DevicePushProvider::class)->shouldNotReceive('send');

    new SendNotificationPush($notification->id)->handle(resolve(DevicePushProvider::class));
});

it('retries only the phones that failed, within the original budget', function (): void {
    Device::factory()->for($this->recipient)->withPushToken()->create();
    $notification = Notification::factory()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->andReturnUsing(fn (Device $device): bool => $device->id !== $this->phone->id);
    Queue::fake();

    new SendNotificationPush($notification->id)->handle(resolve(DevicePushProvider::class));

    Queue::assertPushed(SendNotificationPush::class, fn (SendNotificationPush $retry): bool => $retry->notificationId === $notification->id
        && $retry->deviceIds === [$this->phone->id] && $retry->tries === 2 && $retry->delay->equalTo(now()->addSeconds(30)));
});

it('stops retrying after three attempts in all', function (int $previousAttempts): void {
    $notification = Notification::factory()->create(['recipient_id' => $this->recipient->id, 'actor_id' => $this->actor->id]);
    $this->mock(DevicePushProvider::class)->shouldReceive('send')->times(3 - $previousAttempts)->andReturnFalse();
    dispatch(new SendNotificationPush($notification->id)->onConnection('database')->onQueue('push-retries'));
    DB::table('jobs')->where('queue', 'push-retries')->update(['attempts' => $previousAttempts]);

    for ($attempt = $previousAttempts; $attempt < 3; $attempt++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
        $this->travel(31)->seconds();
    }

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with([0, 1, 2]);
