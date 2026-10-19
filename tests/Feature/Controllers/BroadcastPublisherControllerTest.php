<?php

declare(strict_types=1);

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Models\Conversation;
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

if (! function_exists('promoteToPublisher')) {
    function promoteToPublisher(User $owner, string $broadcast, User $subscriber): void
    {
        test()->actingAs($owner)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $subscriber->username]))->assertNoContent();
    }
}

it('promotes a subscriber to publisher and back', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertJsonPath('can_post', false);

    promoteToPublisher($mark, $broadcast, $nuno);

    $thread = $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertOk();

    expect($thread->json('role'))->toBe('publisher')
        ->and($thread->json('can_post'))->toBeTrue()
        ->and(collect($thread->json('publishers'))->pluck('username')->all())->toBe([$mark->username, $nuno->username]);

    test()->actingAs($nuno)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'from the new publisher', 'client_id' => Str::uuid()->toString()])->assertCreated();

    test()->actingAs($mark)->deleteJson(route('broadcast-publisher.destroy', [$broadcast, 'publisher' => $nuno->username]))->assertNoContent();

    $this->actingAs($nuno)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'still here', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()->assertJsonPath('message', 'Only the owner and publishers can post.');

    $this->getJson(route('broadcast-message.index', $broadcast))->assertOk()->assertJsonPath('role', 'subscriber');

    expect(Conversation::query()->findOrFail($broadcast)->activeBroadcastRoleOf($nuno))->toBe(BroadcastRole::Subscriber);
});

it('keeps publisher choices to the owner', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $taylor = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    subscribeToBroadcast($taylor, $broadcast);

    $this->actingAs($nuno)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $taylor->username]))
        ->assertForbidden()->assertJsonPath('message', 'Only the owner can choose publishers.');
    $this->actingAs(User::factory()->create())->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $taylor->username]))->assertNotFound();
});

it('only promotes subscribers and demotes publishers', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    promoteToPublisher($mark, $broadcast, $nuno);

    $this->actingAs($mark)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $mark->username]))
        ->assertForbidden()->assertJsonPath('message', "@{$mark->username} is not a subscriber.");
    $this->actingAs($mark)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $nuno->username]))
        ->assertForbidden()->assertJsonPath('message', "@{$nuno->username} is not a subscriber.");
    $this->actingAs($mark)->deleteJson(route('broadcast-publisher.destroy', [$broadcast, 'publisher' => $mark->username]))
        ->assertForbidden()->assertJsonPath('message', "@{$mark->username} is not a publisher.");

    expect(Conversation::query()->findOrFail($broadcast)->activeBroadcastRoleOf($nuno))->toBe(BroadcastRole::Publisher);
});

it('keeps a demoted publisher reading, and out of the composer', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    promoteToPublisher($mark, $broadcast, $nuno);
    $this->actingAs($nuno)->putJson(route('broadcast-typing.update', $broadcast))->assertNoContent();

    test()->actingAs($mark)->deleteJson(route('broadcast-publisher.destroy', [$broadcast, 'publisher' => $nuno->username]))->assertNoContent();

    $this->actingAs($nuno)->putJson(route('broadcast-typing.update', $broadcast))->assertNotFound();
    $this->actingAs($mark)->putJson(route('broadcast-typing.update', $broadcast))->assertNoContent();
    $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertOk()->assertJsonPath('typing', [$mark->username]);
});
