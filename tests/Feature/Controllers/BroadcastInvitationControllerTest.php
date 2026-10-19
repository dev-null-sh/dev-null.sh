<?php

declare(strict_types=1);

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

if (! function_exists('createBroadcast')) {
    function createBroadcast(User $owner, string $name = 'ship log', BroadcastVisibility $visibility = BroadcastVisibility::Public): string
    {
        return test()->actingAs($owner)->postJson(route('broadcast.store'), [
            'name' => $name,
            'visibility' => $visibility->value,
        ])->assertCreated()->json('broadcast.id');
    }
}

if (! function_exists('subscribeToBroadcast')) {
    function subscribeToBroadcast(User $subscriber, string $broadcast): void
    {
        test()->actingAs($subscriber)->postJson(route('broadcast-subscription.store', $broadcast))->assertNoContent();
    }
}

if (! function_exists('pushToBroadcast')) {
    function pushToBroadcast(User $sender, string $broadcast, string $body = 'hello'): Message
    {
        test()->actingAs($sender)->postJson(route('broadcast-message.store', $broadcast), [
            'body' => $body,
            'client_id' => Str::uuid()->toString(),
        ])->assertCreated();

        return Message::query()->latest('id')->firstOrFail();
    }
}

it('lets only the owner invite', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $taylor = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();
    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $taylor->username])->assertNoContent();
    $this->actingAs($nuno)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $taylor->username])
        ->assertForbidden()->assertJsonPath('message', 'Only the owner can invite.');
    $this->actingAs(User::factory()->create())->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $taylor->username])->assertNotFound();

    expect(BroadcastInvitation::query()->count())->toBe(1);

    $this->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $taylor->username])->assertNoContent();

    expect(BroadcastInvitation::query()->count())->toBe(1);
});

it('says why someone cannot be invited', function (string $case, string $error): void {
    $mark = User::factory()->create(['username' => 'mark']);
    $nuno = User::factory()->create(['username' => 'nunomaduro']);
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    $candidate = User::factory()->create(['username' => 'candidate']);

    if ($case === 'blocked by the owner') {
        $mark->blocking()->attach($candidate);
    }

    match ($case) {
        'remotes off' => $candidate->update(['remotes_enabled' => false]),
        'already in' => $candidate = $nuno,
        'the owner themselves' => $candidate = $mark,
        default => null,
    };

    $this->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $candidate->username])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['member' => "@{$candidate->username} {$error}."]);
})->with([
    'remotes off' => ['remotes off', 'has remotes off'],
    'blocked by the owner' => ['blocked by the owner', 'cannot be invited across a block'],
    'already in' => ['already in', 'is already in the broadcast'],
    'the owner themselves' => ['the owner themselves', 'is already in the broadcast'],
]);
it('lets the invitee decline, and the owner take an invitation back', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $taylor = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $taylor->username])->assertNoContent();

    $this->actingAs($nuno)->deleteJson(route('broadcast-invitation.destroy', [$broadcast, 'invitee' => $nuno->username]))->assertNoContent();
    $this->actingAs($nuno)->postJson(route('broadcast-subscription.store', $broadcast))
        ->assertForbidden()->assertJsonPath('message', 'This broadcast is invite-only.');

    $this->actingAs($mark)->deleteJson(route('broadcast-invitation.destroy', [$broadcast, 'invitee' => $taylor->username]))->assertNoContent();

    $this->actingAs($taylor)->deleteJson(route('broadcast-invitation.destroy', [$broadcast, 'invitee' => $taylor->username]))->assertNotFound();
    $this->actingAs(User::factory()->create())->deleteJson(route('broadcast-invitation.destroy', [$broadcast, 'invitee' => $nuno->username]))->assertNotFound();

    expect(BroadcastInvitation::query()->count())->toBe(0);
});

it('shows the invitation to its invitee alone', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    pushToBroadcast($mark, $broadcast, 'hidden until accepted');
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();

    $directory = $this->actingAs($nuno)->getJson(route('broadcast.index'))->assertOk()->json('broadcasts');

    expect($directory)->toHaveCount(1)
        ->and($directory[0]['membership'])->toBe('invited');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonCount(0, 'conversations')->assertJsonPath('unread', 0);
});

it('rejects malformed invitations without looking up a member', function (string $member): void {
    $owner = User::factory()->create();
    $broadcast = createBroadcast($owner);

    $this->actingAs($owner)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $member])
        ->assertUnprocessable()->assertJsonValidationErrors('member');
})->with(['', 'no-such-broadcast-invitee']);

it("starts the accepted history at the invitation's arrival", function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    $before = pushToBroadcast($mark, $broadcast, 'before the invitation');
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();

    subscribeToBroadcast($nuno, $broadcast);
    $after = pushToBroadcast($mark, $broadcast, 'after the acceptance');

    $thread = $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertOk();

    expect(collect($thread->json('messages'))->pluck('id')->all())->toBe([$after->id])
        ->and(Conversation::query()->findOrFail($broadcast)->activeBroadcastRoleOf($nuno))->toBe(BroadcastRole::Subscriber);
});
