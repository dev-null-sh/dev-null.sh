<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Jobs\SendRemotePush;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->withRemotes()->create();
});

it('records a ping from a maintainer or contributor with its sender', function (bool $contributor): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);
    if ($contributor) {
        $this->user = $member;
    }

    $response = $this->actingAs($this->user)->postJson(route('org-ping.store', $org))->assertCreated();

    $ping = Message::query()->latest('id')->firstOrFail();
    $response->assertJsonPath('message.id', $ping->id)
        ->assertJsonPath('message.event', 'ping')
        ->assertJsonPath('message.body', '')
        ->assertJsonPath('message.user.handle', $this->user->handle);
    expect($ping->event)->toBe(OrgEvent::Ping)
        ->and($ping->conversation_id)->toBe($org)
        ->and(Conversation::query()->findOrFail($org)->last_message_id)->toBe($ping->id);
    $this->assertDatabaseCount('messages', 2);
})->with([false, true]);

it('hides an org from a nonmember trying to ping it', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$member] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$member]);

    $this->actingAs($this->user)->postJson(route('org-ping.store', $org))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

it('rejects pings from a member with Remotes disabled', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);
    $this->user->update(['remotes_enabled' => false]);

    $this->actingAs($this->user)->postJson(route('org-ping.store', $org))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

it('returns 401 for a guest org ping', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);
    auth()->logout();

    $this->postJson(route('org-ping.store', $org))->assertUnauthorized();

    $this->assertDatabaseCount('messages', 1);
});

it('queues a delayed push for each subscribed member except the sender', function (): void {
    [$first, $second, $unsubscribed] = followersOf($this->user, 3);
    $org = createOrg($this->user, [$first, $second, $unsubscribed]);
    $outsider = User::factory()->withRemotes()->create();
    foreach ([$this->user, $first, $second, $outsider] as $user) {
        PushSubscription::factory()->create(['user_id' => $user->id]);
    }

    Queue::fake([SendRemotePush::class]);

    $response = $this->actingAs($this->user)->postJson(route('org-ping.store', $org))->assertCreated();

    Queue::assertPushed(SendRemotePush::class, 2);
    foreach ([$first, $second] as $recipient) {
        Queue::assertPushed(SendRemotePush::class, fn (SendRemotePush $job): bool => $job->messageId === $response->json('message.id')
            && $job->userId === $recipient->id && $job->afterCommit === true && $job->delay->equalTo(now()->addSeconds(10)));
    }
});
