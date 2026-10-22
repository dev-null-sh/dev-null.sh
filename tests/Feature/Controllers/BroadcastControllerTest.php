<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Str;

if (! function_exists('createBroadcast')) {
    /**
     * Creates a broadcast the way the owner's client does, and returns its id.
     */
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

it('creates a broadcast with an owner', function (): void {
    $mark = User::factory()->create();

    $broadcast = createBroadcast($mark, 'ship log', BroadcastVisibility::Invite);

    $conversation = Conversation::query()->findOrFail($broadcast);

    expect($conversation->kind)->toBe(ConversationKind::Broadcast)
        ->and($conversation->name)->toBe('ship log')
        ->and($conversation->visibility)->toBe(BroadcastVisibility::Invite)
        ->and($conversation->activeBroadcastRoleOf($mark))->toBe(BroadcastRole::Owner)
        ->and($conversation->messages()->count())->toBe(0);

    $this->actingAs($mark)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.kind', 'broadcast')
        ->assertJsonPath('conversations.0.name', 'ship log')
        ->assertJsonPath('conversations.0.last_message', null);
});

it('lists public broadcasts in the directory and hides the invite-only ones', function (): void {
    $mark = User::factory()->create(['username' => 'mark']);
    $nuno = User::factory()->create(['username' => 'nunomaduro']);
    $public = createBroadcast($mark, 'ship log');
    $inviteOnly = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    subscribeToBroadcast($nuno, $public);

    $directory = $this->actingAs(User::factory()->create())->getJson(route('broadcast.index'))->assertOk()->json('broadcasts');

    expect(collect($directory)->pluck('id')->all())->toBe([$public])
        ->and($directory[0]['owner']['username'])->toBe('mark')
        ->and($directory[0]['subscriber_count'])->toBe(2)
        ->and($directory[0]['membership'])->toBe('none');

    $this->actingAs($nuno)->getJson(route('broadcast.index'))
        ->assertJsonPath('broadcasts.0.membership', 'subscriber');

    $this->actingAs($mark)->getJson(route('broadcast.index'))
        ->assertJsonPath('broadcasts.0.membership', 'owner');
});

it('finds broadcasts by name or by owner', function (string $term): void {
    $mark = User::factory()->create(['username' => 'mark']);
    createBroadcast($mark, 'ship log');
    createBroadcast(User::factory()->create(), 'unrelated');

    $this->actingAs(User::factory()->create())->getJson(route('broadcast.index', ['q' => $term]))->assertJsonCount(1, 'broadcasts');
})->with(['ship', 'mark']);

it('keeps the directory listing broadcasts that still have an owner', function (): void {
    $mark = User::factory()->create();
    $kept = createBroadcast(User::factory()->create(), 'ship log');
    createBroadcast($mark, 'ghost ship');

    resolve(DeleteUser::class)->handle($mark);

    $this->actingAs(User::factory()->create())->getJson(route('broadcast.index'))->assertOk()
        ->assertJsonCount(1, 'broadcasts')
        ->assertJsonPath('broadcasts.0.id', $kept);
});

it('hides an invite-only broadcast from the directory once the subscriber left', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();
    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($nuno)->deleteJson(route('broadcast-subscription.destroy', $broadcast))->assertNoContent();

    $this->actingAs($nuno)->getJson(route('broadcast.index'))
        ->assertJsonCount(0, 'broadcasts');
    $this->actingAs($nuno)->getJson(route('broadcast.index', ['q' => 'secret']))
        ->assertJsonCount(0, 'broadcasts');
});

it("shows the owner's public broadcasts on their profile", function (): void {
    $mark = User::factory()->create();
    $public = createBroadcast($mark, 'ship log');
    createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    createBroadcast(User::factory()->create(), "someone else's");

    $response = $this->get("/@{$mark->username}")->assertOk();

    $broadcasts = $response->inertiaProps()['broadcasts'];

    expect($broadcasts)->toBe([['id' => $public, 'name' => 'ship log', 'subscriber_count' => 1]]);
});

it('shows a member the thread and anyone else the broadcast itself', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    $this->actingAs($mark)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'hello', 'client_id' => Str::uuid()->toString()])->assertCreated();

    $props = $this->actingAs($nuno)->get("/remotes/broadcasts/{$broadcast}")->assertOk()->inertiaProps();

    expect($props['broadcast']['membership'])->toBe('subscriber')
        ->and($props['thread']['messages'])->toHaveCount(1)
        ->and($props['thread']['can_post'])->toBeFalse();

    $stranger = $this->actingAs(User::factory()->create())->get("/remotes/broadcasts/{$broadcast}")->assertOk()->inertiaProps();

    expect($stranger['broadcast']['membership'])->toBe('none')
        ->and($stranger['thread'])->toBeNull();
});

it('hides an invite-only broadcast from everyone but members and invitees', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);
    test()->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();

    $this->actingAs(User::factory()->create())->get("/remotes/broadcasts/{$broadcast}")->assertNotFound();
    $this->actingAs(User::factory()->create())->getJson(route('broadcast.index', ['q' => 'secret']))->assertJsonCount(0, 'broadcasts');

    $invited = $this->actingAs($nuno)->get("/remotes/broadcasts/{$broadcast}")->assertOk()->inertiaProps();

    expect($invited['broadcast']['membership'])->toBe('invited')
        ->and($invited['thread'])->toBeNull();
});

it('lets only the owner rename the broadcast and change its visibility', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($nuno)->putJson(route('broadcast.update', $broadcast), ['name' => 'hijacked'])->assertForbidden();

    $this->actingAs($mark)->putJson(route('broadcast.update', $broadcast), ['name' => 'ship log', 'visibility' => 'invite'])->assertNoContent();

    expect(Conversation::query()->findOrFail($broadcast)->visibility)->toBe(BroadcastVisibility::Invite);

    $this->actingAs(User::factory()->create())->getJson(route('broadcast.index'))->assertJsonCount(0, 'broadcasts');
});

it('updates the name and visibility independently', function (): void {
    $mark = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);

    $this->actingAs($mark)->putJson(route('broadcast.update', $broadcast), ['name' => 'renamed crew', 'visibility' => null])->assertNoContent();

    expect(Conversation::query()->findOrFail($broadcast)->name)->toBe('renamed crew')
        ->and(Conversation::query()->findOrFail($broadcast)->visibility)->toBe(BroadcastVisibility::Invite);

    $this->putJson(route('broadcast.update', $broadcast), ['name' => null, 'visibility' => 'public'])->assertNoContent();

    expect(Conversation::query()->findOrFail($broadcast)->name)->toBe('renamed crew')
        ->and(Conversation::query()->findOrFail($broadcast)->visibility)->toBe(BroadcastVisibility::Public);
});

it('keeps people without remotes out', function (): void {
    $mark = User::factory()->create();

    $this->actingAs(User::factory()->create(['remotes_enabled' => false]))->postJson(route('broadcast.store'), ['name' => 'nope', 'visibility' => 'public'])->assertNotFound();
    $this->actingAs(User::factory()->create(['remotes_enabled' => false]))->getJson(route('broadcast.index'))->assertNotFound();
});

it('identifies broadcasts by uuids', function (): void {
    $mark = User::factory()->create();

    $broadcast = createBroadcast($mark);

    expect($broadcast)->toBeUuid();

    $this->actingAs($mark)->get('/remotes/broadcasts/1')->assertNotFound();
});
