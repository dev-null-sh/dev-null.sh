<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\DeleteConversationCall;
use App\Actions\UpdateConversationCall;
use App\Enums\OrgEvent;
use App\Models\Message;
use App\Models\User;
use App\Queries\CallQuery;

beforeEach(function (): void {
    $this->caller = User::factory()->withRemotes()->create();
    $this->callee = User::factory()->withRemotes()->create();
    $this->conversation = sendChatMessage($this->caller, $this->callee)->conversation;
});

it('records who started and answered a call without storing signaling data', function (): void {
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=caller");
    resolve(UpdateConversationCall::class)->handle($this->callee, $this->caller, "v=0\r\no=caller", "v=0\r\no=callee");

    $events = Message::query()->where('conversation_id', $this->conversation->id)->whereNotNull('event')->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe([OrgEvent::CallStarted, OrgEvent::CallAnswered])
        ->and($events->pluck('user_id')->all())->toBe([$this->caller->id, $this->callee->id])
        ->and($events->pluck('body')->all())->toBe(['', ''])
        ->and($this->conversation->fresh()->last_message_id)->toBe($events->last()->id);

    $this->assertDatabaseCount('messages', 3);
});

it('records a cancelled ringing call only once', function (): void {
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=caller");
    resolve(DeleteConversationCall::class)->handle($this->caller, $this->callee);
    resolve(DeleteConversationCall::class)->handle($this->caller, $this->callee);

    expect(Message::query()->whereNotNull('event')->pluck('event')->all())
        ->toBe([OrgEvent::CallStarted, OrgEvent::CallCancelled])
        ->and(resolve(CallQuery::class)->get($this->callee))->toBeNull();
});

it('records a declined ringing call with the callee as actor', function (): void {
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=caller");
    resolve(DeleteConversationCall::class)->handle($this->callee, $this->caller);

    $event = Message::query()->where('event', OrgEvent::CallDeclined)->firstOrFail();

    expect($event->user_id)->toBe($this->callee->id)
        ->and(resolve(CallQuery::class)->get($this->callee))->toBeNull();
});

it('does not treat clearing answered signaling as a cancelled call', function (): void {
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=caller");
    resolve(UpdateConversationCall::class)->handle($this->callee, $this->caller, "v=0\r\no=caller", "v=0\r\no=callee");
    resolve(DeleteConversationCall::class)->handle($this->caller, $this->callee);

    expect(Message::query()->whereNotNull('event')->pluck('event')->all())
        ->toBe([OrgEvent::CallStarted, OrgEvent::CallAnswered]);
});

it('does not add a history entry without a ringing call', function (): void {
    resolve(DeleteConversationCall::class)->handle($this->caller, $this->callee);

    expect(Message::query()->whereNotNull('event')->count())->toBe(0);
});

it('does not duplicate a call history entry when the same caller retries signaling', function (): void {
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=first");
    resolve(CreateConversationCall::class)->handle($this->caller, $this->callee, "v=0\r\no=second");

    expect(Message::query()->where('event', OrgEvent::CallStarted)->count())->toBe(1)
        ->and(resolve(CallQuery::class)->get($this->callee)['offer'])->toBe("v=0\r\no=second");
});
