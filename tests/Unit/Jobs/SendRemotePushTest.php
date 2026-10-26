<?php

declare(strict_types=1);

use App\Actions\CreateMessage;
use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Jobs\SendRemotePush;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\RemotePush;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

beforeEach(function (): void {
    $keys = VAPID::createVapidKeys();
    config(['services.web_push.public_key' => $keys['publicKey'], 'services.web_push.private_key' => $keys['privateKey']]);
    $this->message = Message::factory()->create(['body' => 'Private content must never reach a push service']);
    $this->recipient = User::factory()->create();
    $this->participant = ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->recipient->id]);
    $this->subscription = PushSubscription::query()->create(['user_id' => $this->recipient->id, 'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/wp/recipient'), 'endpoint' => 'https://fcm.googleapis.com/wp/recipient']);
    $this->pushStatus = 201;
    $this->pushStatuses = [];
    $this->connectionFails = false;
    Http::fake(['https://fcm.googleapis.com/*' => function (Request $request) {
        throw_if($this->connectionFails, ConnectionException::class, 'unavailable');

        return Http::response('', $this->pushStatuses[$request->url()] ?? $this->pushStatus);
    }]);
});

it('sends an authenticated empty push to every eligible browser without message data', function (): void {
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'second', 'endpoint' => 'https://fcm.googleapis.com/wp/second']);
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->message->user_id]);
    $this->message->user->pushSubscriptions()->create(['endpoint_hash' => 'sender', 'endpoint' => 'https://fcm.googleapis.com/wp/sender']);
    resolve(SendRemotePush::class, ['messageId' => $this->message->id, 'userId' => $this->recipient->id])->handle(resolve(RemotePush::class));
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->body() === '' && str_starts_with($request->header('Authorization')[0], 'vapid t=') && $request->header('TTL') === ['300']);
});

it('does not load recipients who have no push subscriptions', function (): void {
    $this->subscription->delete();
    $loaded = [];
    Event::listen('eloquent.retrieved: '.User::class, function (User $user) use (&$loaded): void {
        $loaded[] = $user->id;
    });

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));

    expect($loaded)->not->toContain($this->recipient->id);
    Http::assertNothingSent();
});

it('delivers a ping event as an empty push without message data', function (): void {
    $this->message->update(['event' => OrgEvent::Ping, 'body' => '']);

    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->body() === '');
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
        'unconfigured' => config(['services.web_push.private_key' => null]),
    };
    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));
    Http::assertNothingSent();
})->with(['read', 'history', 'muted', 'dropped', 'fetched', 'left', 'disabled', 'blocked', 'blocked-by', 'user-muted', 'deleted', 'old', 'event', 'unconfigured']);

it('allows expired mutes and enabled fetched requests', function (): void {
    config(['remotes.requests' => true]);
    $this->participant->update(['muted_until_at' => now(), 'status' => RemoteStatus::Fetched]);
    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));
    Http::assertSentCount(1);
});

it('removes expired endpoints but keeps other subscriptions', function (int $status, bool $removed): void {
    $this->pushStatus = $status;
    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));
    expect(PushSubscription::query()->whereKey($this->subscription->id)->exists())->toBe(! $removed);
})->with([[404, true], [410, true], [403, false], [429, false], [500, false]]);

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

    resolve(CreateMessage::class)->handle($this->message->user, $this->message->conversation, 'new message', Str::uuid()->toString(), linkPreview: false);
    $this->travel(11)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    Http::assertSentCount(20);
    $this->assertDatabaseCount('jobs', 1);

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    Http::assertSentCount(40);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('retries temporary push failures without keeping or reporting the endpoint', function (int $status, bool $connectionFails): void {
    $this->pushStatus = $status;
    $this->connectionFails = $connectionFails;
    Queue::fake();
    $job = new SendRemotePush($this->message->id, $this->recipient->id);
    $job->handle(resolve(RemotePush::class));
    $this->assertModelExists($this->subscription);
    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $retry): bool => $retry->messageId === $this->message->id && $retry->userId === $this->recipient->id
        && $retry->subscriptionIds === [$this->subscription->id] && $retry->tries === 2 && $retry->delay->equalTo(now()->addSeconds(30)));
})->with([[500, false], [429, false], [201, true]]);

it('retries only failed browsers across serialized queue deliveries', function (): void {
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'second', 'endpoint' => 'https://fcm.googleapis.com/wp/second']);
    $other = User::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $other->id]);
    $other->pushSubscriptions()->create(['endpoint_hash' => 'other', 'endpoint' => 'https://fcm.googleapis.com/wp/other']);
    $this->pushStatuses['https://fcm.googleapis.com/wp/recipient'] = 500;
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    dispatch(new SendRemotePush($this->message->id, $other->id)->onConnection('database')->onQueue('push-other'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-other', '--once' => true])->assertSuccessful();

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
    Http::assertSentCount(3);

    $this->pushStatuses['https://fcm.googleapis.com/wp/recipient'] = 201;
    $other->pushSubscriptions()->create(['endpoint_hash' => 'new', 'endpoint' => 'https://fcm.googleapis.com/wp/new']);
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    Http::assertSentCount(4);
    expect(Http::recorded()->filter(fn (array $record): bool => $record[0]->url() === 'https://fcm.googleapis.com/wp/recipient'))->toHaveCount(2);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('stops temporary retries within the original three-attempt budget', function (int $previousAttempts): void {
    $this->pushStatus = 500;
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    DB::table('jobs')->where('queue', 'push-retries')->update(['attempts' => $previousAttempts]);

    for ($attempt = $previousAttempts; $attempt < 3; $attempt++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();
        $this->travel(31)->seconds();
    }

    Http::assertSentCount(3 - $previousAttempts);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with([0, 1, 2]);

it('rechecks eligibility and enrollment before a failed browser retry', function (string $change): void {
    $this->pushStatus = 500;
    dispatch(new SendRemotePush($this->message->id, $this->recipient->id)->onConnection('database')->onQueue('push-retries'));
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    match ($change) {
        'read' => $this->participant->update(['read_through_id' => $this->message->id]),
        'revoked' => $this->subscription->delete(),
        'transferred' => $this->subscription->update(['user_id' => User::factory()->create()->id]),
    };
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'push-retries', '--once' => true])->assertSuccessful();

    Http::assertSentCount(1);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
})->with(['read', 'revoked', 'transferred']);

it('uses Windows raw push headers for Microsoft Edge endpoints', function (): void {
    Http::fake(['https://wns2-example.notify.windows.com/*' => Http::response('', 201)]);
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'edge', 'endpoint' => 'https://wns2-example.notify.windows.com/w/?token=example']);
    new SendRemotePush($this->message->id, $this->recipient->id)->handle(resolve(RemotePush::class));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '.notify.windows.com/') && $request->header('X-WNS-Type') === ['wns/raw'] && $request->header('Content-Type') === ['application/octet-stream'] && $request->header('Urgency') === [] && $request->body() === '');
});
