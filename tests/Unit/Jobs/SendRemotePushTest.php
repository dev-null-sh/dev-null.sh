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
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
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
    $this->connectionFails = false;
    Http::fake(['https://fcm.googleapis.com/*' => function () {
        throw_if($this->connectionFails, ConnectionException::class, 'unavailable');

        return Http::response('', $this->pushStatus);
    }]);
});

it('sends an authenticated empty push to every eligible browser without message data', function (): void {
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'second', 'endpoint' => 'https://fcm.googleapis.com/wp/second']);
    ConversationParticipant::factory()->create(['conversation_id' => $this->message->conversation_id, 'user_id' => $this->message->user_id]);
    $this->message->user->pushSubscriptions()->create(['endpoint_hash' => 'sender', 'endpoint' => 'https://fcm.googleapis.com/wp/sender']);
    resolve(SendRemotePush::class, ['messageId' => $this->message->id])->handle(resolve(RemotePush::class));
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->body() === '' && str_starts_with($request->header('Authorization')[0], 'vapid t=') && $request->header('TTL') === ['300']);
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
    new SendRemotePush($this->message->id)->handle(resolve(RemotePush::class));
    Http::assertNothingSent();
})->with(['read', 'history', 'muted', 'dropped', 'fetched', 'left', 'disabled', 'blocked', 'blocked-by', 'user-muted', 'deleted', 'old', 'event', 'unconfigured']);

it('allows expired mutes and enabled fetched requests', function (): void {
    config(['remotes.requests' => true]);
    $this->participant->update(['muted_until_at' => now(), 'status' => RemoteStatus::Fetched]);
    new SendRemotePush($this->message->id)->handle(resolve(RemotePush::class));
    Http::assertSentCount(1);
});

it('removes expired endpoints but keeps other subscriptions', function (int $status, bool $removed): void {
    $this->pushStatus = $status;
    new SendRemotePush($this->message->id)->handle(resolve(RemotePush::class));
    expect(PushSubscription::query()->whereKey($this->subscription->id)->exists())->toBe(! $removed);
})->with([[404, true], [410, true], [403, false], [429, false], [500, false]]);

it('queues delivery once for a retried message and only after commit', function (): void {
    Queue::fake();
    $clientId = Str::uuid()->toString();
    $action = resolve(CreateMessage::class);
    $message = $action->handle($this->message->user, $this->message->conversation, 'new message', $clientId, linkPreview: false);
    $action->handle($this->message->user, $this->message->conversation, 'new message', $clientId, linkPreview: false);
    Queue::assertPushed(SendRemotePush::class, 1);
    Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $job): bool => $job->messageId === $message->id && $job->afterCommit === true && $job->delay->equalTo(now()->addSeconds(10)));
});

it('retries temporary push failures without keeping or reporting the endpoint', function (int $status, bool $connectionFails): void {
    $this->pushStatus = $status;
    $this->connectionFails = $connectionFails;
    $queued = Mockery::mock(Job::class);
    $queued->shouldReceive('release')->once()->with(30);
    $job = new SendRemotePush($this->message->id);
    $job->setJob($queued);
    $job->handle(resolve(RemotePush::class));
    $this->assertModelExists($this->subscription);
})->with([[500, false], [429, false], [201, true]]);

it('uses Windows raw push headers for Microsoft Edge endpoints', function (): void {
    Http::fake(['https://wns2-example.notify.windows.com/*' => Http::response('', 201)]);
    $this->recipient->pushSubscriptions()->create(['endpoint_hash' => 'edge', 'endpoint' => 'https://wns2-example.notify.windows.com/w/?token=example']);
    new SendRemotePush($this->message->id)->handle(resolve(RemotePush::class));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '.notify.windows.com/') && $request->header('X-WNS-Type') === ['wns/raw'] && $request->header('Content-Type') === ['application/octet-stream'] && $request->header('Urgency') === [] && $request->body() === '');
});
